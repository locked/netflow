netflow
=======

Requirements
------------

For all:

- mongodb

For the probe:

- python-dpkt
- python-pypcap

For the frontend:

- php-mongo (pecl install mongo)

Installation
------------

Run the probe on all server:

python probe.py [-i<interface>] [-s<mongodb server>] [-f<pcap filter>]
