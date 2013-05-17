import pcap
import dpkt
import sys
import string
import time
import socket
import threading
import re
from pymongo import Connection
from optparse import OptionParser

networks = [
  {'re':re.compile('192.168.\d+.\d+'), 'zone':'internal'},
  {'re':re.compile('10.\d+.\d+.\d+'), 'zone':'internal'},
  {'re':re.compile('172.16.\d+.\d+'), 'zone':'internal'},
  {'re':re.compile('169.254.\d+.\d+'), 'zone':'autoconf'},
]
def find_zone(ip):
	for n in networks:
		if re.match(n['re'], ip):
			return n['zone']
	return 'default'



class Main:
	agg = {}

	def __init__(self, options):
		self.options = options
		self.db_connect()
		self.lock = threading.Lock()


	# DB
	def db_connect(self):
		try:
			self.connection = Connection(self.options.server, 27017)
			db = self.connection.network
			self.flows = db.flows
		except Exception as e:
			print "Connection error [%s]" % str(e)

	def db_insert(self, doc):
		try:
			si = self.connection.server_info()
			#print "Server Info [%s]" % str(si)
		except:
			self.db_connect()
		try:
			self.flows.insert(doc)
		except Exception as e:
			print "Insert error [%s]" % str(e)


	# Save aggragated data to DB
	def dump(self):
		if self.agg:
			self.lock.acquire()
			try:
				msg = {'ts':int(time.time()), 'data':self.agg}
				print msg
				tosave_docs = []
				for key, vals in self.agg.items():
					keys = key.split("-")
					doc = {'ts':int(time.time()), 'src':keys[0], 'dst':keys[1], 'vals':vals, 'sum':sum([v for k,v in vals.items()])}
					tosave_docs.append(doc)
				self.agg = {}
			except Exception as e:
				print "Error in dump(): [%s]" % str(e)
			finally:
				self.lock.release()
			# Insert in DB after lock release
			try:
				for doc in tosave_docs:
					self.db_insert(doc)
			except Exception as e:
				print "Error while saving data: [%s]" % str(e)
		t = threading.Timer(5.0, self.dump)
		t.daemon = True
		t.start()


	# Network listening
	def go(self):
		self.dump()
		try:
			pc = pcap.pcap(name=self.options.iface)
		except Exception as e:
			print str(e)
			return False
		pc.setfilter(self.options.filter)
		for ts, pkt in pc:
			p = dpkt.ethernet.Ethernet(pkt)
			ip = p.data
			#print `ip`
			if p.type==dpkt.ethernet.ETH_TYPE_IP: # IP
				src = socket.inet_ntoa(ip.src)
				dst = socket.inet_ntoa(ip.dst)
				size = int(ip.len)
				proto = str(ip.p)
				if ip.p==dpkt.ip.IP_PROTO_TCP:
					proto = 'TCP'
				elif ip.p==dpkt.ip.IP_PROTO_UDP:
					proto = 'UDP'
				elif ip.p==dpkt.ip.IP_PROTO_IP6:
					proto = 'IPv6'
				src_zone = find_zone(src)
				dst_zone = find_zone(dst)
				#print "%d: %s(%s) => %s(%s) [%d] [%s]" % (int(ts), src, src_zone, dst, dst_zone, size, proto)

				key = (src_zone if src_zone=="default" else src) + "-" + (dst_zone if dst_zone=="default" else dst)
				
				self.lock.acquire()
				try:
					if key not in self.agg: self.agg[key] = {}
					if proto not in self.agg: self.agg[key][proto] = 0
					self.agg[key][proto] += size
				except Exception as e:
					print "Error in go(): [%s]" % str(e)
				finally:
					self.lock.release()


if __name__=='__main__':
    parser = OptionParser()
    parser.add_option("-i", "--iface", dest="iface", default="eth0", help="Network interface")
    parser.add_option("-f", "--filter", dest="filter", default="", help="Filter string")
    parser.add_option("-s", "--server", dest="server", default="localhost", help="Database server address")
    (options, args) = parser.parse_args()

    m = Main(options)
    m.go()
