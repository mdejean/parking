<?php

$conn = new PDO('pgsql:dbname=parking');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

@mkdir("data/order/", 0777, true);

$grouped = $conn->query("
select
    l.boro,
    l.order_no,
    l.main_st,
    l.from_st,
    l.to_st,
    l.sos,
    os.blockface,
    ST_AsLatLonText(ST_Transform(ST_ClosestPoint(closest_on.geom, closest_from.geom), 4326), 'D.DDDDD') from_pt,
    ST_AsLatLonText(ST_Transform(ST_ClosestPoint(closest_on.geom, closest_to.geom), 4326), 'D.DDDDD') to_pt
from invalid_order io
left join order_segment os on io.order_no = os.order_no
join location l on l.order_no = io.order_no
join (values ('M', 1), ('X', 2), ('K', 3), ('Q', 4), ('S', 5)) b (borochar, borocode) on l.boro = b.borochar
left join lateral (
    select
        ss.street,
        ST_Collect(ss.geom order by ss.facecode, ss.seqnum) geom
    from street_segment ss
    where ss.lboro = b.borocode
    and ss.street like left(l.main_st, 1) || '%'
    and levenshtein_less_equal(l.main_st, ss.street, 10, 1, 5, 50) < 50
    group by ss.street
    order by levenshtein(l.main_st, ss.street, 10, 1, 5)
    limit 1
) closest_on on true
left join lateral (
    select
        ss.street,
        ST_Collect(ss.geom order by ss.facecode, ss.seqnum) geom
    from street_segment ss
    where ss.lboro = b.borocode
    and ss.street like left(l.from_st, 1) || '%'
    and levenshtein_less_equal(l.from_st, ss.street, 10, 1, 5, 50) < 50
    group by ss.street
    order by levenshtein(l.from_st, ss.street, 10, 1, 5)
    limit 1
) closest_from on true
left join lateral (
    select
        ss.street,
        ST_Collect(ss.geom order by ss.facecode, ss.seqnum) geom
    from street_segment ss
    where ss.lboro = b.borocode
    and ss.street like left(l.to_st, 1) || '%'
    and levenshtein_less_equal(l.to_st, ss.street, 10, 1, 5, 50) < 50
    group by ss.street
    order by levenshtein(l.to_st, ss.street, 10, 1, 5)
    limit 1
) closest_to on true
")->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

$boros = ['' => 0, 'M' => 1, 'B' => 2, 'K' => 3, 'Q' => 4, 'S' => 5];

foreach ($grouped as $cboro => $locations) {
    $boro = $boros[$cboro];
    
    file_put_contents('data/order/' . $boro . '.json', json_encode($locations));
}