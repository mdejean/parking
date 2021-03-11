<?php

$conn = new PDO('pgsql:dbname=parking');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

@mkdir("data/order/", 0777, true);

$grouped = $conn->query('
select
    l.boro,
    l.order_no,
    l.main_st,
    l.from_st,
    l.to_st,
    l.sos,
    os.blockface
from invalid_order io 
left join location l on io.order_no = l.order_no
left join order_segment os on io.order_no = os.order_no
')->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

$boros = ['' => 0, 'M' => 1, 'B' => 2, 'K' => 3, 'Q' => 4, 'S' => 5];

foreach ($grouped as $cboro => $locations) {
    $boro = $boros[$cboro];
    
    file_put_contents('data/order/' . $boro . '.json', json_encode($locations));
}