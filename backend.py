#!/usr/bin/python

from bottle import route, run, request, abort, Bottle ,static_file
from gevent import monkey; monkey.patch_all()
from time import sleep
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
            start_ts = init_vals['start_ts']
            interval = init_vals['interval'] if 'interval' in init_vals else 5
            if interval<1: interval = 1
            # db connection
            connection = Connection(db_host, 27017)
            db = connection.network
            while True:
                # Get data from db
                docs = db.flows.find({"ts": {"$gte":start_ts}, "v":0.2})
                data = []
                for d in docs:
                    d['_id'] = ""
                    data.append(d)
                # Send data
                wsock.send(json.dumps(data))
                sleep(interval)
                start_ts = start_ts + interval
        except WebSocketError:
            break

host = "0.0.0.0"
port = 8080

server = WSGIServer((host, port), app, handler_class=WebSocketHandler)
server.serve_forever()

