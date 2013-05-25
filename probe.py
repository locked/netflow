import pcap
import dpkt
import sys
import string
import time
import socket
import threading
import re
import logging
import ConfigParser
from pymongo import Connection
from optparse import OptionParser


class Main:
	pkt_sum = {}
	pkt_sum_agg = {}

	def __init__(self, config):
		self.config = config
		self.db_connect()
		self.lock = threading.Lock()
		self.local_ip = self.get_ip()
		self.networks = [
		  {'re':re.compile('192.168.\d+.\d+'), 'zone':'internal'},
		  {'re':re.compile('10.\d+.\d+.\d+'), 'zone':'internal'},
		  {'re':re.compile('172.16.\d+.\d+'), 'zone':'internal'},
		  {'re':re.compile('169.254.\d+.\d+'), 'zone':'autoconf'},
		]
		# setup log
		logging.basicConfig(filename=self.get_config('log'), level=logging.DEBUG, format='%(asctime)s %(message)s')


	def get_config(self, name, section='general', default=''):
		return self.config.get(section, name) if self.config.has_option(section, name) else default


	def find_zone(self, ip):
		for n in self.networks:
			if re.match(n['re'], ip):
				return n['zone']
		return 'default'


	# DB
	def db_connect(self):
		try:
			self.connection = Connection(self.get_config('server'), 27017)
			db = self.connection.network
			self.flows = db.flows
		except Exception as e:
			logging.error("Connection error [%s]" % str(e))

	def db_insert(self, doc):
		try:
			si = self.connection.server_info()
			#print "Server Info [%s]" % str(si)
		except:
			self.db_connect()
		try:
			self.flows.insert(doc)
		except Exception as e:
			logging.error("Insert error [%s]" % str(e))


	# Save aggragated data to DB
	def dump(self):
		if self.pkt_sum:
			self.lock.acquire()
			try:
				msg = {'ts':int(time.time()), 'data':self.pkt_sum}
				logging.debug(str(msg))
				tosave_docs = []
				for key, vals in self.pkt_sum.items():
					keys = key.split("-")
					doc = {'v': 0.2, 'ts':int(time.time()), 'src':keys[0], 'dst':keys[1], 'vals':vals, 'sum':self.pkt_sum_agg[key]}
					#print doc
					tosave_docs.append(doc)
				self.pkt_sum = {}
				self.pkt_sum_agg = {}
			except Exception as e:
				logging.error("Error in dump(): [%s]" % str(e))
			finally:
				self.lock.release()
			# Insert in DB after lock release
			try:
				for doc in tosave_docs:
					self.db_insert(doc)
			except Exception as e:
				logging.error("Error while saving data: [%s]" % str(e))
		t = threading.Timer(5.0, self.dump)
		t.daemon = True
		t.start()

	def get_ip(self):
		s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM) 
		s.connect(('google.com', 0)) 
		return s.getsockname()[0]

	# Network listening
	def go(self):
		self.dump()
		try:
			pc = pcap.pcap(name=self.get_config('iface'))
		except Exception as e:
			logging.error(str(e))
			logging.error(str(e))
			return False
		filters = []
		if self.get_config('mode')=="local":
			filters.append("(src %s or dst %s)" % (self.local_ip, self.local_ip))
		if self.get_config('filter')<>"":
			filters.append(self.get_config('filter'))
		filter_str = " and ".join(filters)
		logging.info("Start listening with the following params:")
		logging.info("iface [%s]" % self.get_config('iface'))
		logging.info("filter [%s]" % filter_str)
		pc.setfilter(filter_str)
		for ts, pkt in pc:
			p = dpkt.ethernet.Ethernet(pkt)
			ip = p.data
			#print `ip`
			if p.type==dpkt.ethernet.ETH_TYPE_IP: # IP
				src = socket.inet_ntoa(ip.src)
				dst = socket.inet_ntoa(ip.dst)
				size = int(ip.len)
				proto_ip = str(ip.p)
				sport = "0"
				dport = "0"
				if ip.p==dpkt.ip.IP_PROTO_TCP:
					proto_ip = 'TCP'
					sport = str(ip.data.sport)
					dport = str(ip.data.dport)
				elif ip.p==dpkt.ip.IP_PROTO_UDP:
					proto_ip = 'UDP'
					sport = str(ip.data.sport)
					dport = str(ip.data.dport)
				elif ip.p==dpkt.ip.IP_PROTO_IP6:
					proto_ip = 'IPv6'
				src_zone = self.find_zone(src)
				dst_zone = self.find_zone(dst)
				#print ip.data.sport
				#print "%d: %s(%s) => %s(%s) [%d] [ip:%s] [dport:%d]" % (int(ts), src, src_zone, dst, dst_zone, size, proto_ip, dport)

				key = (src_zone if src_zone=="default" else src) + "-" + (dst_zone if dst_zone=="default" else dst)
				
				self.lock.acquire()
				try:
					if key not in self.pkt_sum: self.pkt_sum[key] = {}
					if proto_ip not in self.pkt_sum[key]: self.pkt_sum[key][proto_ip] = {'dport':{}, 'sport':{}}
					if dport not in self.pkt_sum[key][proto_ip]['dport']: self.pkt_sum[key][proto_ip]['dport'][dport] = 0
					if sport not in self.pkt_sum[key][proto_ip]['sport']: self.pkt_sum[key][proto_ip]['sport'][sport] = 0
					self.pkt_sum[key][proto_ip]["dport"][dport] += size
					self.pkt_sum[key][proto_ip]["sport"][sport] += size
					#print self.pkt_sum

					if key not in self.pkt_sum_agg: self.pkt_sum_agg[key] = {}
					if proto_ip not in self.pkt_sum_agg[key]: self.pkt_sum_agg[key][proto_ip] = 0
					self.pkt_sum_agg[key][proto_ip] += size
				except Exception as e:
					print str(e)
					logging.error("Error in go(): [%s]" % str(e))
				finally:
					self.lock.release()


if __name__=='__main__':
	parser = OptionParser()
	parser.add_option("-i", "--iface", dest="iface", default="eth0", help="Network interface (eth0/wlan0/...)")
	parser.add_option("-f", "--filter", dest="filter", default="", help="Filter string")
	parser.add_option("-s", "--server", dest="server", default="localhost", help="Database server address")
	parser.add_option("-m", "--mode", dest="mode", default="local", help="Mode (full/local)")
	parser.add_option("-c", "--config", dest="config", default="/etc/netflow/probe.conf", help="Config file")
	parser.add_option("-l", "--log", dest="log", default="/var/log/netflow-probe.log", help="Log file")
	(options, args) = parser.parse_args()

	config = ConfigParser.ConfigParser()
	try:
		config.readfp(open(options.config))
	except Exception as e:
		print str(e), "Will use default config."
	if not config.has_section("general"):
		config.add_section("general")
	opts = ["iface", "filter", "server", "mode", "log"]
	for o in opts:
		if not config.has_option("general", o):
			config.set("general", o, getattr(options, o))

	m = Main(config)
	m.go()
