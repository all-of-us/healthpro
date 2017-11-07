from __future__ import print_function
import httplib2
import os,sys
import json
import getopt

from apiclient import discovery
from oauth2client import client
from oauth2client import tools
from oauth2client.file import Storage
from googleapiclient.discovery import build

from oauth2client.service_account import ServiceAccountCredentials
from datetime import date, timedelta,datetime

# If modifying these scopes, delete your previously saved credentials
# at ~/.credentials/admin-directory_v1-python-quickstart.json
DIR_SCOPES = ['https://www.googleapis.com/auth/admin.directory.group','https://www.googleapis.com/auth/admin.directory.user']
REPORT_SCOPES = ['https://www.googleapis.com/auth/admin.reports.audit.readonly','https://www.googleapis.com/auth/admin.reports.usage.readonly']
SERVICE_ACCOUNT_USER='usermanager@pmiopsmanage.iam.gserviceaccount.com'
CLIENT_SECRET_FILE = '/Users/dbernick/Downloads/xxxxx'
DELEGATE_ACCOUNT = 'dbernick@pmi-ops.org'
APPLICATION_NAME = 'Directory API Python Quickstart'
GOOGLE_2FA_EXCEPTION_GROUP = 'mfa_exception@pmi-ops.org'
INACTIVEDAYS=60
DRYRUN=False

def create_directory_service(user_email):
    global CLIENT_SECRET_FILE
    credentials = ServiceAccountCredentials.from_json_keyfile_name(CLIENT_SECRET_FILE, DIR_SCOPES)
    delegated_credentials = credentials.create_delegated(DELEGATE_ACCOUNT)
    http_auth = delegated_credentials.authorize(httplib2.Http())


    return build('admin', 'directory_v1', http=http_auth)


def create_report_service(user_email):
    global CLIENT_SECRET_FILE
    credentials = ServiceAccountCredentials.from_json_keyfile_name(CLIENT_SECRET_FILE, REPORT_SCOPES)
    delegated_credentials = credentials.create_delegated(DELEGATE_ACCOUNT)
    http_auth = delegated_credentials.authorize(httplib2.Http())


    return build('admin', 'reports_v1', http=http_auth)


def getInactveReportResults(daycount):
    reportservice = create_report_service(SERVICE_ACCOUNT_USER)

    results = None
    while results is None:
        try:
            # connect
            #
            activeSince = date.today() - timedelta(days=daycount)
            results = reportservice.userUsageReport().get(userKey=useremail,date=yesterday.strftime('%Y-%m-%d'),parameters='accounts:is_2sv_enrolled').execute()
        except:
            daycount=daycount+1

    return results,daycount

def main(argv):
    global CLIENT_SECRET_FILE
    global DRYRUN
    try:
       opts, args = getopt.getopt(argv,"hdc:",["cfile="])
    except getopt.GetoptError:
       print("test.py -c CLIENT_SECRET_FILE")
       sys.exit(2)
    for opt, arg in opts:
       if opt == '-h':
          print('test.py -c <client_secret_path>')
          sys.exit()
       elif opt in ("-c", "--cfile"):
          CLIENT_SECRET_FILE = arg
       elif opt in ("-d"):
          DRYRUN = True
    dirservice = create_directory_service(SERVICE_ACCOUNT_USER)
    page_token=None
    new_page_token=None
    loopAgain=True
    users=[]
    usersToDisable=[]
    while loopAgain:
        results = dirservice.users().list(domain = 'pmi-ops.org',orderBy='email',maxResults=10,pageToken=page_token).execute()
        page_token=results.get('nextPageToken',None)
        loopAgain=False
        if page_token:
            loopAgain=True
        for u in results.get('users', []):
            users.append(u)
            lastLogin=datetime.strptime(u.get('lastLoginTime',None),"%Y-%m-%dT%H:%M:%S.%fZ")
            creationtime=datetime.strptime(u.get('creationTime',None),"%Y-%m-%dT%H:%M:%S.%fZ")
            print(u.get('primaryEmail',None))
            print("Days since create: %s" % (datetime.today()-creationtime).days)
            print("Days since last login: %s" % (datetime.today()-lastLogin).days)
            if (datetime.today()-creationtime).days==-1:
                continue
            if ((datetime.today()-lastLogin).days > INACTIVEDAYS and (datetime.today()-creationtime).days > INACTIVEDAYS+1) or ((datetime.today()-lastLogin).days > 2000 and (datetime.today()-creationtime).days > INACTIVEDAYS):
                usersToDisable.append(u)
                continue

    for user in usersToDisable:
        if user["suspended"]:
            continue
        if not DRYRUN:
            user["suspended"]=True
            user["suspensionReason"]="Inactivity"
            print("User to suspend %s" % user.get('primaryEmail',None))
            dirservice.users().update(userKey=user.get('primaryEmail',None),body=user).execute()
            print("Suspending %s" % user.get('primaryEmail',None))
            continue
        print("DryRun Status: %s - %s" % (DRYRUN,user.get('primaryEmail',None)))

if __name__ == "__main__":
   main(sys.argv[1:])

