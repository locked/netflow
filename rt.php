<!doctype>
<head>
	<link type="text/css" rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css">
	<link type="text/css" rel="stylesheet" href="css/rt.css">

	<script src="js/d3.v2.min.js"></script>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.2/jquery.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.15/jquery-ui.min.js"></script>

	<script src="js/rickshaw.min.js"></script>

	<script src="js/extensions.js"></script>
</head>
<body>

<div id="content">
	<form id="side_panel">
		<h1>Nodes</h1>
		<section><div id="legend"></div></section>
	</form>

	<div id="chart_container">
		<div id="chart"></div>
		<div id="timeline"></div>
		<div id="slider"></div>
	</div>
</div>

<script>
// set up our data series with 150 random data points
var seriesData = []; //[ [], [] ]; //, [], [], [], [], [], [], [], [] ];
var graph;
/*
var random = new Rickshaw.Fixtures.RandomData(2);
for (var i = 0; i < 2; i++) {
	random.addData(seriesData);
}
*/

var init_done = false;

var uds = ['up', 'down'];

var nodes = ["192.168.0.10", "default"];
for( n in nodes ) {
	seriesData.push([]);
	seriesData.push([]);
}

function create_graph(nodes, seriesData) {

var palette = new Rickshaw.Color.Palette( { scheme: 'classic9' } );

var series = [];
var k = 0;
for( n in nodes ) {
	var node = nodes[n];
	for( ud in uds ) {
		serie = {
			color: palette.color(),
			data: seriesData[k],
			name: node+uds[ud],
		}
		series.push(serie);
		k++;
	}
}
//console.log(series);

// instantiate our graph!
var graph = new Rickshaw.Graph( {
	element: document.getElementById("chart"),
	width: 900,
	height: 500,
	renderer: 'line',
	stroke: true,
	preserve: true,
	series: series, /*[
		{
			color: palette.color(),
			data: seriesData[0],
			name: '192.168.0.10'
		}
		, {
			color: palette.color(),
			data: seriesData[1],
			name: 'default'
		}, {
			color: palette.color(),
			data: seriesData[2],
			name: 'Amsterdam'
		}, {
			color: palette.color(),
			data: seriesData[3],
			name: 'Paris'
		}, {
			color: palette.color(),
			data: seriesData[4],
			name: 'Tokyo'
		}, {
			color: palette.color(),
			data: seriesData[5],
			name: 'London'
		}, {
			color: palette.color(),
			data: seriesData[6],
			name: 'New York'
		}
	]*/
} );

graph.render();

var slider = new Rickshaw.Graph.RangeSlider( {
	graph: graph,
	element: $('#slider')
} );

var hoverDetail = new Rickshaw.Graph.HoverDetail( {
	graph: graph
} );

var annotator = new Rickshaw.Graph.Annotate( {
	graph: graph,
	element: document.getElementById('timeline')
} );

var legend = new Rickshaw.Graph.Legend( {
	graph: graph,
	element: document.getElementById('legend')

} );
var shelving = new Rickshaw.Graph.Behavior.Series.Toggle( {
	graph: graph,
	legend: legend
} );
var order = new Rickshaw.Graph.Behavior.Series.Order( {
	graph: graph,
	legend: legend
} );
var highlighter = new Rickshaw.Graph.Behavior.Series.Highlight( {
	graph: graph,
	legend: legend
} );
/*
var smoother = new Rickshaw.Graph.Smoother( {
	graph: graph,
	element: $('#smoother')
} );
*/
var ticksTreatment = 'glow';

var xAxis = new Rickshaw.Graph.Axis.Time( {
	graph: graph,
	ticksTreatment: ticksTreatment
} );

xAxis.render();

var yAxis = new Rickshaw.Graph.Axis.Y( {
	graph: graph,
	tickFormat: Rickshaw.Fixtures.Number.formatKMBT,
	ticksTreatment: ticksTreatment
} );

yAxis.render();


var controls = new RenderControls( {
	element: document.querySelector('form'),
	graph: graph
} );

	init_done = true;

	return graph;
}


var ws = new WebSocket("ws://192.168.0.10:8080/rt");
var start_ts = Math.floor(new Date().getTime() / 1000);
ws.onopen = function() {
        ws.send("{\"start_ts\":"+start_ts+", \"start_shift\": 600, \"interval\": 30}");
};
ws.onmessage = function (evt) {
	eval("var data = "+evt.data+";");
	//console.log(data);
	var k = 0;
	for( n in nodes ) {
		node = nodes[n];
		//console.log(node+" SERIES:" + data[node]);
		if( data[node] ) {
			series = data[node];
			for( ud in uds ) {
				for( ts in series ) {
					node_data = series[ts];
					//console.log(ts + "  DOWN:" + node_data["down"]);
					seriesData[k].push( {x: parseInt(ts), y: parseInt(node_data[uds[ud]]) } );
				}
				k++;
			}
		}
	}
	if( !init_done ) {
		graph = create_graph(nodes, seriesData);
	} else {
		graph.update();
	}
};


/*
setInterval( function() {
	random.addData(seriesData);
	graph.update();

}, 3000 );
*/


/*
var messages = [
	"Changed home page welcome message",
	"Minified JS and CSS",
	"Changed button color from blue to green",
	"Refactored SQL query to use indexed columns",
	"Added additional logging for debugging",
	"Fixed typo",
	"Rewrite conditional logic for clarity",
	"Added documentation for new methods"
];
function addAnnotation(force) {
	if (messages.length > 0 && (force || Math.random() >= 0.95)) {
		annotator.add(seriesData[2][seriesData[2].length-1].x, messages.shift());
	}
}

addAnnotation(true);
setTimeout( function() { setInterval( addAnnotation, 6000 ) }, 6000 );
*/

</script>

</body>
