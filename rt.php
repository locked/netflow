<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

<script type="text/javascript">
var ws = new WebSocket("ws://192.168.0.10:8080/rt");
var start_ts = 1369520134 - 3600;
ws.onopen = function() {
        ws.send("{\"start_ts\":"+start_ts+"}");
};
ws.onmessage = function (evt) {
        console.log("ADD DATA:" + evt.data);
};
</script>

</head>
<body>
</body>
</html>
