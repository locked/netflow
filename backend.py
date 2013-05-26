#!/usr/bin/python

from bottle import route, run, request, abort, Bottle ,static_file
from gevent import monkey; monkey.patch_all()
#from time import sleep
import time
import json
from gevent.pywsgi import WSGIServer
from geventwebsocket import WebSocketHandler, WebSocketError
from pymongo import Connection

db_host = "192.168.0.10"

app = Bottle()

@app.route('/rt')
def handle_websocket():
    wsock = request.environ.get('wsgi.websocket')
    if not wsock:
        abort(400, 'Expected WebSocket request.')
    while True:
        try:
            # init params
            init = wsock.receive()
            init_vals = json.loads(init)
            start_ts = int(time.time())-10 #init_vals['start_ts']
            interval = init_vals['interval'] if 'interval' in init_vals else 5
            if interval<1: interval = 1
            start_shift = init_vals['start_shift'] if 'start_shift' in init_vals else 60
            start_ts -= start_shift
            # db connection
            connection = Connection(db_host, 27017)
            db = connection.network
            while True:
                # Get data from db
                print start_ts, time.time()
                docs = db.flows.find({"ts": {"$gte":start_ts}, "v":0.2})
                #data = []
                bynodes = {}
                #min_ts = start_ts + interval*2
                #max_ts = 0
                for d in docs:
                    #if d["ts"]>max_ts: max_ts = d["ts"]
                    #if d["ts"]<min_ts: min_ts = d["ts"]
                    ts = int(round((float(d['ts'])/float(interval)))*int(interval));

                    node = d['src']
                    up_bytes = d['sum']['TCP'] if 'TCP' in d['sum'] else 0
                    if node not in bynodes: bynodes[node] = {}
                    if ts not in bynodes[node]: bynodes[node][ts] = {}
                    if 'up' not in bynodes[node][ts]:
                        bynodes[node][ts]['up'] = [0, []]
                    bynodes[node][ts]['up'][0] = bynodes[node][ts]['up'][0] + up_bytes
                    bynodes[node][ts]['up'][1].append( d['ts'] )

                    node = d['dst']
                    if d['src']=="default" and node=="192.168.0.36": print d
                    down_bytes = d['sum']['TCP'] if 'TCP' in d['sum'] else 0
                    if node not in bynodes: bynodes[node] = {}
                    if ts not in bynodes[node]: bynodes[node][ts] = {}
                    if 'down' not in bynodes[node][ts]:
                        bynodes[node][ts]['down'] = [0, []]
                    bynodes[node][ts]['down'][0] = bynodes[node][ts]['down'][0] + down_bytes
                    bynodes[node][ts]['down'][1].append( d['ts'] )

                    #data.append(d)

                # Normalize
                for node, tss in bynodes.items():
                    for ts, d in tss.items():
                        for ud in ['up', 'down']:
                            if ud not in d: continue
                            elapsed = float(max(d[ud][1]) - min(d[ud][1]))
                            #print d[ud][1], max(d[ud][1]), min(d[ud][1])
                            v = bynodes[node][ts][ud][0] / elapsed if elapsed>0 else 0
                            bynodes[node][ts][ud] = v

                # Send data
                #print bynodes
                wsock.send(json.dumps(bynodes))
                time.sleep(interval)
                start_ts += interval + start_shift
                start_shift = 0
        except WebSocketError:
            break

host = "0.0.0.0"
port = 8080

server = WSGIServer((host, port), app, handler_class=WebSocketHandler)
server.serve_forever()

