<!DOCTYPE html>
<meta charset="utf-8">
<link href="bootstrap/css/bootstrap.css" rel="stylesheet">
<link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet">
<script src="http://d3js.org/d3.v2.min.js?2.9.3"></script>
<style>
body {
	padding-top: 60px;
}
.node {
  stroke: #000;
  stroke-width: 1.5px;
}
/*
.link {
  stroke: #ccc;
  stroke-opacity: .6;
}
*/
.node text {
  pointer-events: none;
  font: 10px sans-serif;
}
</style>
<body><div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="brand" href="#">NetFlow</a>
          <div class="nav-collapse collapse">
            <ul class="nav">
              <li class="active"><a href="#">Home</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

    <div class="container">

		<form method="GET">
		<?php
		$protocols = array("ALL", "TCP", "UDP");
		$protocol = $_REQUEST["protocol"]!=""?$_REQUEST["protocol"]:"ALL";
		?>
		<select name="protocol">
			<?php foreach($protocols as $p): ?>
			<option<?= $p==$protocol?" selected":"" ?>><?= $p ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn" style="vertical-align:top;">Update</button>
		</form>

		<div class="flow"></div>

    </div> <!-- /container -->

<script>
function drawCurve(d) {
        var slope = Math.atan2((+d3.select('#circle' + d.target).attr("cy") - d3.select('#circle' + d.source).attr("cy")), (+d3.select('#circle' + d.target).attr("cx") - d3.select('#circle' + d.source).attr("cx")));
        var slopePlus90 = Math.atan2((+d3.select('#circle' + d.target).attr("cy") - d3.select('#circle' + d.source).attr("cy")), (+d3.select('#circle' + d.target).attr("cx") - d3.select('#circle' + d.source).attr("cx"))) + (Math.PI / 2);
        var sourceX = +d3.select('#circle' + d.source).attr("cx");
        var sourceY = +d3.select('#circle' + d.source).attr("cy");
        var targetX = +d3.select('#circle' + d.target).attr("cx");
        var targetY = +d3.select('#circle' + d.target).attr("cy");

        var arrowOffset = 20;
        var points = [];
points.push([sourceX + radius * Math.cos(slope) - strength_scale(d.strength) * Math.cos(slopePlus90), sourceY + radius * Math.sin(slope) - strength_scale(d.strength) * Math.sin(slopePlus90)]);
        points.push([sourceX + radius * Math.cos(slope), sourceY + radius * Math.sin(slope)]);
        points.push([targetX - radius * Math.cos(slope), targetY - radius * Math.sin(slope)]);
        points.push([targetX - (radius + arrowOffset) * Math.cos(slope) - strength_scale(d.strength + (arrowOffset / 2)) * Math.cos(slopePlus90), targetY - (radius + arrowOffset) * Math.sin(slope) - strength_scale(d.strength + (arrowOffset / 2)) * Math.sin(slopePlus90)]);
        points.push([targetX - (radius + arrowOffset) * Math.cos(slope) - strength_scale(d.strength) * Math.cos(slopePlus90), targetY - (radius + arrowOffset) * Math.sin(slope) - strength_scale(d.strength) * Math.sin(slopePlus90)]);
        return d3LineLinear(points) + "Z";
}


var width = 900,
    height = 700;
var radius = 5;
var linkDistance = 180;
var charge = 480;

var data_url = "<?= "network.php?protocol=".$protocol; ?>";

var color = d3.scale.category20();

var svg = d3.select(".flow").append("svg")
    .attr("width", width)
    .attr("height", height);

var force = d3.layout.force()
    .gravity(.05)
    .distance(linkDistance)
    .charge(-charge)
    .size([width, height]);


// Per-type markers, as they don't inherit styles.
svg.append("svg:defs").selectAll("marker")
    .data(["src-dst", "dst-src"])
  .enter().append("svg:marker")
    .attr("id", String)
    .attr("viewBox", "0 -5 10 10")
    .attr("refX", radius*2+1)
    .attr("refY", 0)
    //.attr("markerUnits","strokeWidth")
    .attr("markerWidth", 4)
    .attr("markerHeight", 4)
    .attr("orient", "auto")
  .append("svg:path")
    .attr("d", "M0,-5L10,0L0,5")
    .attr("fill", function(d) { return d=="src-dst"?"#FF0000":"#0000FF" });


d3.json(data_url, function(json) {
  force
      .nodes(json.nodes)
      .links(json.links)
      .start();

  /*
  var link = svg.selectAll(".link")
      .data(json.links)
    .enter().append("line")
      .attr("class", "link")
      .style("stroke-width", function(d) { return Math.sqrt(d.value); })
      .style("stroke", function(d) { return d3.rgb(d.dir=="src-dst"?"#FF0000":"#0000FF"); });
  */

  var link = svg.selectAll(".link")
      .data(json.links)
    .enter().append("path")
      .attr("class", "link")
      //.attr("fill", function(d) { return d3.rgb("#FF0000"); })
      .attr("marker-end", function(d) { return "url(#" + d.dir + ")"; })
      .style("stroke", function(d) { return d3.rgb(d.dir=="src-dst"?"#FF0000":"#0000FF"); })
      .style("stroke-width", function(d) { return Math.sqrt(d.value); })
      .style("fill", "none");
      //.attr("id", function(i, d) { return "link_line" + d; });

  var node = svg.selectAll(".node")
      .data(json.nodes)
    .enter().append("g")
      .attr("id", function(i, d) { return "circle" + i })
      .call(force.drag);

  node.append("circle")
      .attr("class", "node")
      .attr("r", radius)
      .style("fill", function(d) { return color(d.group); });

  node.append("text")
      .attr("dx", radius+1)
      .attr("dy", ".35em")
      .text(function(d) { return d.name });

  force.on("tick", function() {
    link.attr("d", function(d) {
	  var dx = d.target.x - d.source.x,
		dy = d.target.y - d.source.y,
		dr = Math.sqrt(dx * dx + dy * dy);
	  return "M" + d.source.x + "," + d.source.y + "A" + dr + "," + dr + " 0 0,1 " + d.target.x + "," + d.target.y;
    });
  
    /*
    link.attr("x1", function(d) { return d.source.x; })
        .attr("y1", function(d) { return d.source.y; })
        .attr("x2", function(d) { return d.target.x; })
        .attr("y2", function(d) { return d.target.y; });
	*/
	
    node.attr("transform", function(d) { return "translate(" + d.x + "," + d.y + ")"; });
  });
});

</script>
