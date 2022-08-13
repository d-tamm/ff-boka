<?php
require "../inc/common.php";
$sections = $FF->getAllSections( $_GET[ 'sectionId' ], "n2s" );
$homeSec = array_shift( $sections );
$color = "blue";

?><html>
<head>
    <script src="<?= $cfg[ 'url' ] ?>vendor/components/jquery/jquery.min.js"></script>
</head>
<body>
    <div style="height:100%;" id="mapdiv"></div>
    <script src="../inc/openlayers/ol.js"></script>
    <link rel="stylesheet" href="../inc/openlayers/ol.css">
    <script>
        var homeStyle = new ol.style.Style({
            image: new ol.style.Icon({
                anchor: [0.5, 0.9],
                src: "../resources/pin_home.png",
                scale: .3,
            }),
            text: new ol.style.Text({
                font: 'bold 18px Calibri,sans-serif',
                fill: new ol.style.Fill({ color: '#000' }),
                stroke: new ol.style.Stroke({ color: '#fff', width: 2 }),
                text: '<?= $homeSec->name ?>',
                offsetY: 15,
            }),
        });
        function otherStyle( feature, resolution ) {
            return [
                new ol.style.Style({
                    image: new ol.style.Icon({
                        anchor: [ 0.5, 0.9 ],
                        src: "../resources/pin.png",
                        scale: .2,
                    }),
                    text: new ol.style.Text( {
                        font: '12px Calibri,sans-serif',
                        fill: new ol.style.Fill( { color: '#000' } ),
                        stroke: new ol.style.Stroke( { color: '#fff', width: 2 } ),
                        text: resolution<250 ? feature.get( 'description' ) : '',
                        offsetY: 10,
                    } ),
                } )
            ];
        }

        var vectorSource = new ol.source.Vector( { features: [  ] } );

        <?php foreach ( $sections as $s ) { ?>
            var marker = new ol.Feature( {
                geometry: new ol.geom.Point( ol.proj.fromLonLat( [ <?= $s->lon ?>, <?= $s->lat ?> ] ) ),
                description: '<?= $s->name ?>',
            } );
            marker.setStyle( otherStyle );
            vectorSource.addFeature( marker );
        <?php } ?>

        var homeMarker = new ol.Feature( {
            geometry: new ol.geom.Point( ol.proj.fromLonLat( [ <?= $homeSec->lon ?>, <?= $homeSec->lat ?> ] ) ),
            name: "home",
        } );
        homeMarker.setStyle( homeStyle );
        vectorSource.addFeature( homeMarker );

        var baseMapLayer = new ol.layer.Tile( { source: new ol.source.OSM } );
        var markers = new ol.layer.Vector( { source: vectorSource } );

        map = new ol.Map( {
            target: "mapdiv",
            layers: [ baseMapLayer, markers ],
            view: new ol.View( {
                center: ol.proj.fromLonLat( [ <?= $homeSec->lon ?>, <?= $homeSec->lat ?> ] ),
                zoom: 10,
            } )
        } );
  </script>
  </body></html>