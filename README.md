netflow
=======

Very simple network flow aggregator & visualization tool. Run a probe on each of your network and visualize TCP and/or UDP flows :)

![Alt text](/netflow.png "screen")


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

- Run the probe on all server: python probe.py [-i&lt;interface&gt;] [-s&lt;mongodb server&gt;] [-f&lt;pcap filter&gt;]
- Configure apache/nginx/whatever to access the frontend 'index.php' file.
