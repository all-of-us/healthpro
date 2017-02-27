from __future__ import print_function
import httplib2
import os
import json

from apiclient import discovery
from oauth2client import client
from oauth2client import tools
from oauth2client.file import Storage
from googleapiclient.discovery import build

from oauth2client.service_account import ServiceAccountCredentials
from datetime import date, timedelta,datetime
#pip install httplib2 boto google-api-python-client

try:
    import argparse
    flags = argparse.ArgumentParser(parents=[tools.argparser]).parse_args()
except ImportError:
    flags = None

# If modifying these scopes, delete your previously saved credentials
# at ~/.credentials/admin-directory_v1-python-quickstart.json
DIR_SCOPES = ['https://www.googleapis.com/auth/admin.directory.group','https://www.googleapis.com/auth/admin.directory.user']
REPORT_SCOPES = ['https://www.googleapis.com/auth/admin.reports.audit.readonly','https://www.googleapis.com/auth/admin.reports.usage.readonly']
SERVICE_ACCOUNT_USER='usermanager@pmiopsmanage.iam.gserviceaccount.com'
CLIENT_SECRET_FILE = '/Users/dbernick/Downloads/PMIOpsManage-d1bfcf0991ed.json'
DELEGATE_ACCOUNT = 'dbernick@pmi-ops.org'
APPLICATION_NAME = 'Directory API Python Quickstart'
GOOGLE_2FA_EXCEPTION_GROUP = 'mfa_exception@pmi-ops.org'
INACTIVEDAYS=60

def create_directory_service(user_email):

    credentials = ServiceAccountCredentials.from_json_keyfile_name(CLIENT_SECRET_FILE, DIR_SCOPES)
    delegated_credentials = credentials.create_delegated(DELEGATE_ACCOUNT)
    http_auth = delegated_credentials.authorize(httplib2.Http())


    return build('admin', 'directory_v1', http=http_auth)


def create_report_service(user_email):

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

def main():
    dirservice = create_directory_service(SERVICE_ACCOUNT_USER)
    #groupresults = dirservice.members().list(groupKey=GOOGLE_2FA_EXCEPTION_GROUP).execute()
    #google_2fa_members=[]
    #for member in groupresults.get('members'):
    #    google_2fa_members.append(member.get('email').encode("utf-8"))
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
            if (datetime.today()-lastLogin).days > INACTIVEDAYS:
                usersToDisable.append(u)

    for user in usersToDisable:
        print("Suspending %s" % user.get('primaryEmail',None))
        user["suspended"]=True
        user["suspensionReason"]="Inactivity"
        dirservice.users().update(userKey=user.get('primaryEmail',None),body=user).execute()
        print(user)

if __name__ == '__main__':
    main()
