from __future__ import print_function
import httplib2
import os

from apiclient import discovery
from oauth2client import client
from oauth2client import tools
from oauth2client.file import Storage
from googleapiclient.discovery import build

from oauth2client.service_account import ServiceAccountCredentials

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

def create_directory_service(user_email):
    """Build and returns an Admin SDK Directory service object authorized with the service accounts
    that act on behalf of the given user.

    Args:
      user_email: The email of the user. Needs permissions to access the Admin APIs.
    Returns:
      Admin SDK directory service object.
    """

    credentials = ServiceAccountCredentials.from_json_keyfile_name(CLIENT_SECRET_FILE, DIR_SCOPES)
    delegated_credentials = credentials.create_delegated(DELEGATE_ACCOUNT)
    http_auth = delegated_credentials.authorize(httplib2.Http())

    #credentials = credentials.create_delegated(user_email)

    return build('admin', 'directory_v1', http=http_auth)


def create_report_service(user_email):

    credentials = ServiceAccountCredentials.from_json_keyfile_name(CLIENT_SECRET_FILE, REPORT_SCOPES)
    delegated_credentials = credentials.create_delegated(DELEGATE_ACCOUNT)
    http_auth = delegated_credentials.authorize(httplib2.Http())

    #del_credentials = credentials.create_delegated(user_email)

    return build('admin', 'reports_v1', http=http_auth)

def getNo2FAUsers(users,google_2fa_members):
    reportservice = create_report_service(SERVICE_ACCOUNT_USER)
    userlist=[]
    removeList=[]
    if not users:
        print('No users in the domain.')
    else:
        for user in users:
            userEmail = user['primaryEmail'].encode('utf-8')
            results = reportservice.userUsageReport().get(userKey=user['primaryEmail'],date='2016-10-23',parameters='accounts:is_2sv_enrolled').execute()
            #print(results.get('usageReports'))
            if results.get('usageReports'):
                for item in results.get('usageReports'):
                    if item.get("parameters"):
                        for par in item.get("parameters"):
                            #if user has not activated 2fa and is in 2fa_exception group, list
                            #if user has activated 2fa and is in 2fa exception group, remove from exception group
                            if not par.get("boolValue") and userEmail in google_2fa_members:
                                #print('{0} ({1})'.format(userEmail,par.get("boolValue")))
                                userlist.append(userEmail)
                            elif par.get("boolValue") and userEmail in google_2fa_members:
                                removeList.append(userEmail)
            else:
                #print('{0} ({1})'.format(userEmail,False))
                userlist.append(userEmail)
    return userlist,removeList

def main():
    dirservice = create_directory_service(SERVICE_ACCOUNT_USER)
    groupresults = dirservice.members().list(groupKey=GOOGLE_2FA_EXCEPTION_GROUP).execute()
    google_2fa_members=[]
    for member in groupresults.get('members'):
        google_2fa_members.append(member.get('email').encode("utf-8"))

    results = dirservice.users().list(domain = 'pmi-ops.org',orderBy='email').execute()
    users = results.get('users', [])
    no2faUsers,removeList = getNo2FAUsers(users,google_2fa_members)

    for user in removeList:
        print("Removing %s" % user)
        dirservice.members().list(groupKey=GOOGLE_2FA_EXCEPTION_GROUP,memberKey=user).execute()


if __name__ == '__main__':
    main()
