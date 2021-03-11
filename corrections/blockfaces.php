<?php

$conn = new PDO('pgsql:dbname=parking');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


@mkdir("data/blockface/", 0777, true);

$grouped = $conn->query('
select
    left(bctcb2010, 1) boro,
    blockface,
    ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geom
from blockface_geom bg
')->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

foreach ($grouped as $boro => $blockfaces) {
    $all = [];
    foreach ($blockfaces as $row) {
        $all[$row['blockface']] = json_decode($row['geom']);
    }
    
    file_put_contents('data/blockface/' . $boro . '.json', json_encode($all));
}