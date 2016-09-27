#!/usr/bin/python
# Outputs a dos.yaml file to whitelist IPs

from netaddr import *
from geoip import geolite2
import urllib,urllib2,json

# Retrieve data from Imperva
url="https://my.incapsula.com/api/integration/v1/ips"
data = urllib.urlencode({'content':'json'})
req = urllib2.Request(url, data)
response = urllib2.urlopen(req)
result= json.loads(response.read())

# IPv4 and IPv6 ranges
imperva_whitelist = result["ipRanges"] + result["ipv6Ranges"]

# Initial blacklist is *all nets*
blacklist = IPSet(['0.0.0.0/0'])
blacklist_ipv6 = IPSet(['::/0'])

# Remove allowed nets from the whitelist
for net in imperva_whitelist:
	net = IPNetwork(net)
	country = geolite2.lookup(str(net.ip)).country
	if(country == "US"):
		if(net.version == 4):
			blacklist.remove(net)
		if(net.version == 6):
			blacklist_ipv6.remove(net)

# Join the two IPSets together
blacklist = IPSet(blacklist.iter_cidrs() + blacklist_ipv6.iter_cidrs())

# Print the blacklist
print "blacklist:"
for net in blacklist.iter_cidrs():
	print "- subnet: " + str(net)
