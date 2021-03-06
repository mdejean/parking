<?php

$conn = new PDO('pgsql:dbname=parking');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function cmd($k) {
    global $argv;
    return isset($_GET[$k]) or (isset($argv[1]) and $argv[1] == $k);
}

function array_set($value, &$a, ...$keys) {
    $current = &$a;
    foreach ($keys as $k) {
        if (!isset($current[$k])) {
            $current[$k] = [];
        }
        $current = &$current[$k];
    }
    $current = $value;
}

if (cmd('boroughs')) {
    $boro_parking = $conn->query('select
        cb.borocode,
        p.day,
        p.period,
        p.type,
        sum(p.length) length,
        sum(p.spaces) spaces
from parking p
join order_segment os on os.order_no = p.order_no
left join blockface_geom bg on bg.blockface = os.blockface
join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
group by cb.borocode, p.day, p.period, p.type')->fetchAll(PDO::FETCH_ASSOC);
    $boro_geom = $conn->query('select
    b.borocode,
    b.boroname as name,
    ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geom,
    s.*,
    uncoded.*,
    offstreet.*
    from borough b
    left join (
        select
            ct.borocode,
            sum(cs.vehicles) vehicles,
            sqrt(sum(cs.vehicles_error * cs.vehicles_error)) vehicles_error,
            sum(cs.population) population,
            sqrt(sum(cs.population_error * cs.population_error)) population_error,
            sum(cs.workers) workers,
            sqrt(sum(cs.workers_error * cs.workers_error)) workers_error,
            sum(cs.workers_drive_alone) workers_drive_alone,
            sqrt(sum(cs.workers_drive_alone_error * cs.workers_drive_alone_error)) workers_drive_alone_error
        from census_tract ct
        left join census_stat cs on ct.boroct2010 = cs.boroct2010
        group by ct.borocode
    ) s on b.borocode = s.borocode
    left join lateral (
        select
            sum(floor(st_length(bg.geom) / 18) * parking_lanes / 2) uncoded_spaces,
            sum(st_length(bg.geom) * parking_lanes / 2) uncoded_ft
        from blockface_geom bg
        left join order_segment os on bg.blockface = os.blockface
        where os.order_no is null and left(bg.bctcb2010, 1) = b.borocode
    ) uncoded on true
    left join lateral (
        select
            sum(coalesce(op.spaces, floor((op.area / 150) ^ 0.63))) offstreet_spaces,
            sum(case when op.source = \'D\' then op.spaces else 0 end) public_spaces
        from offstreet_parking op
        where left(op.bctcb2010, 1) = b.borocode
    ) offstreet on true')->fetchAll(PDO::FETCH_ASSOC);
    
    $all = [];
    foreach ($boro_geom as $row) {
        $borocode = $row['borocode'];
        unset($row['borocode']);
        
        $row['geom'] = json_decode($row['geom']);
        $all[$borocode] = $row;
    }
    
    foreach ($boro_parking as $row) {
        array_set((int)$row['length'], $all, $row['borocode'], 'parking_length', $row['day'], $row['period'], $row['type']);
        array_set((int)$row['spaces'], $all, $row['borocode'], 'parking_spaces', $row['day'], $row['period'], $row['type']);
    }
    
    @mkdir("data", 0777, true);
    file_put_contents("data/boroughs.json", json_encode($all));
}

if (cmd('tracts')) {
    $tract_parking = $conn->prepare('
        select
                cb.ct2010,
                p.day,
                p.period,
                p.type,
                sum(p.length) length,
                sum(p.spaces) spaces
        from parking p
        join order_segment os on os.order_no = p.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
        where cb.borocode = :boro
        group by cb.ct2010, p.day, p.period, p.type');
    $tract_geom = $conn->prepare('
        select 
            ct2010, 
            ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geom,
            cs.*,
            uncoded.*,
            offstreet.*
        from census_tract ct
        left join census_stat cs on ct.boroct2010 = cs.boroct2010
        left join lateral (
            select
                sum(floor(st_length(bg.geom) / 18) * parking_lanes / 2) uncoded_spaces,
                sum(st_length(bg.geom) * parking_lanes / 2) uncoded_ft
            from blockface_geom bg
            left join order_segment os on bg.blockface = os.blockface
            where os.order_no is null and left(bg.bctcb2010, 7) = ct.boroct2010
        ) uncoded on true
        left join lateral (
            select
                sum(coalesce(op.spaces, floor((op.area / 150) ^ 0.63))) offstreet_spaces,
                sum(case when op.source = \'D\' then op.spaces else 0 end) public_spaces
            from offstreet_parking op
            where left(op.bctcb2010, 7) = ct.boroct2010
        ) offstreet on true
        where ct.borocode = :boro');
    $block_geom = $conn->prepare('
        select 
            cb2010, 
            ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geom,
            uncoded.*,
            offstreet.*
        from census_block cb
        left join lateral (
            select
                sum(floor(st_length(bg.geom) / 18) * parking_lanes / 2) uncoded_spaces,
                sum(st_length(bg.geom) * parking_lanes / 2) uncoded_ft
            from blockface_geom bg
            left join order_segment os on bg.blockface = os.blockface
            where os.order_no is null and bg.bctcb2010 = cb.bctcb2010
        ) uncoded on true
        left join lateral (
            select
                sum(coalesce(op.spaces, floor((op.area / 150) ^ 0.63))) offstreet_spaces,
                sum(case when op.source = \'D\' then op.spaces else 0 end) public_spaces
            from offstreet_parking op
            where op.bctcb2010 = cb.bctcb2010
        ) offstreet on true
        where borocode = :boro and ct2010 = :ct2010');
    $block_parking = $conn->prepare('
        select
                cb.cb2010,
                p.day,
                p.period,
                p.type,
                sum(p.length) length,
                sum(p.spaces) spaces
        from parking p
        join order_segment os on os.order_no = p.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
        where cb.borocode = :boro
        and cb.ct2010 = :ct2010
        group by cb.cb2010, p.day, p.period, p.type');
        
    $block_offstreet = $conn->prepare('
        select
            cb.cb2010,
            op.bbl,
            op.area,
            op.spaces,
            op.source,
            ST_AsGeoJSON(
                ST_Transform(
                   op.geom,
                   4326
                ), 6, 0
            ) geom
        from offstreet_parking op
        join census_block cb on cb.bctcb2010 = op.bctcb2010
        where cb.borocode = :boro
        and cb.ct2010 = :ct2010
        order by op.bctcb2010, op.bbl
    ');
    foreach ([1, 2, 3, 4, 5] as $boro) {
        echo "\n$boro:\n";
        
        $all = [];
        
        $tract_geom->execute(['boro' => $boro]);
        $tg = $tract_geom->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tg as $row) {
            $ct2010 = $row['ct2010'];
            unset($row['ct2010']);
            
            $row['geom'] = json_decode($row['geom']);
            
            $all[$ct2010] = $row;
        }
        
        $tract_parking->execute(['boro' => $boro]);
        $tp = $tract_parking->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tp as $row) {
            array_set((int)$row['length'], $all, $row['ct2010'], 'parking_length', $row['day'], $row['period'], $row['type']);
            array_set((int)$row['spaces'], $all, $row['ct2010'], 'parking_spaces', $row['day'], $row['period'], $row['type']);
        }
        
        @mkdir("data/$boro",0777, true);
        file_put_contents("data/$boro/tracts.json", json_encode($all));
        
        $tracts = array_keys($all);
        unset($all);
        
        $tract_no = 0;

        foreach ($tracts as $tract) {
            $block_geom->execute(['boro' => $boro, 'ct2010' => $tract]);
            $result = $block_geom->fetchAll(PDO::FETCH_ASSOC);
            $all = [];
            foreach ($result as $row) {
                $cb2010 = $row['cb2010'];
                unset($row['cb2010']);
                
                $row['geom'] = json_decode($row['geom']);
                $row['offstreet'] = [];
                
                $all[$cb2010] = $row;
            }
            
            $block_parking->execute(['boro' => $boro, 'ct2010' => $tract]);
            $result = $block_parking->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                array_set((int)$row['length'], $all, $row['cb2010'], 'parking_length', $row['day'], $row['period'], $row['type']);
                array_set((int)$row['spaces'], $all, $row['cb2010'], 'parking_spaces', $row['day'], $row['period'], $row['type']);
            }
            
            $block_offstreet->execute(['boro' => $boro, 'ct2010' => $tract]);
            $offstreet_parking = $block_offstreet->fetchAll(PDO::FETCH_ASSOC);
            foreach ($offstreet_parking as $row) {
                $cb2010 = $row['cb2010'];
                unset($row['cb2010']);
                
                $row['geom'] = json_decode($row['geom']);
                
                $all[$cb2010]['offstreet'][] = $row;
            }
            
            
            @mkdir("data/$boro/$tract",0777, true);
            file_put_contents("data/$boro/$tract/blocks.json", json_encode($all));
            
            $tract_no++;
            echo "\t$tract_no/" . count($tracts) . " tracts\r";
        }
    }
    echo "\n";
}

if (cmd('blocks')) {
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $census_blocks = $conn->query('select bctcb2010 from census_block b group by bctcb2010;')->fetchAll(PDO::FETCH_COLUMN);
    $conn->beginTransaction();
    
    $get_signs = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            os.order_no,
            s.seq,
            s.distx,
            s.arrow,
            s.mutcd_code,
            ST_AsGeoJSON(
                ST_Transform(
                    coalesce(
--TODO: make a way to see the parking_regulation_shapefile points (theyre bad)
--                        pr.geom, 
                        MultiLineInterpolatePoint(
                            bg.geom, 
                            case
                                when os.reversed then 1 - s.distx/ST_Length(bg.geom)
                                else s.distx/ST_Length(bg.geom)
                            end
                        )
                    ), 4326
                ), 6, 0
            ) geom
        from sign s
        join order_segment os on os.order_no = s.order_no
        left join (select * from blockface_geom natural join b) bg on bg.blockface = os.blockface
        left join parking_regulation pr on s.order_no = pr.order_no and s.seq = pr.seq
        join b on coalesce(bg.bctcb2010, os.bctcb2010) = b.bctcb2010
        order by os.order_no, s.seq
    ');
    $get_geometry = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            coalesce(os.order_no, bg.blockface) order_no,
            l.main_st,
            l.from_st,
            l.to_st,
            l.sos,
            max(io.error) error,
            ST_AsGeoJSON(ST_Transform(ST_Union(geom), 4326), 6, 0) geom,
            round(ST_Length(ST_Union(geom))) length,
            sum(case when os.order_no is null then floor(st_length(bg.geom) / 18) * parking_lanes / 2 end) uncoded_spaces,
            sum(case when os.order_no is null then st_length(bg.geom) * parking_lanes / 2 end) uncoded_ft,
            max(parking_lanes) parking_lanes
        from (select * from blockface_geom natural join b) bg
        full outer join order_segment os on bg.blockface = os.blockface
        left join location l on l.order_no = os.order_no
        left join invalid_order io on l.order_no = io.order_no
        join b on coalesce(bg.bctcb2010, os.bctcb2010) = b.bctcb2010
        group by coalesce(os.order_no, bg.blockface), l.main_st, l.from_st, l.to_st, l.sos
    ');
    $get_parking = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            os.order_no,
            p.day,
            p.period,
            p.type,
            sum(p.length) length,
            sum(p.spaces) spaces
        from order_segment os
        left join (select * from blockface_geom natural join b) bg on bg.blockface = os.blockface
        join parking p on p.order_no = os.order_no
        join b on b.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
        group by os.order_no, p.day, p.period, p.type');
    
    $block_count = 0;
    
    if (isset($argv[2])) {
        $census_blocks = array_slice($argv,2);
    }
    
    foreach ($census_blocks as $census_block) {
        $borough = substr($census_block, 0, 1);
        $tract = substr($census_block, 1, 6);
        $block = substr($census_block, 7);
        @mkdir("data/$borough/$tract",0777, true);
        
        $get_signs->execute(['bctcb2010' => $census_block]);
        $signs = $get_signs->fetchAll(PDO::FETCH_ASSOC);
        
        $get_geometry->execute(['bctcb2010' => $census_block]);
        $geometry = $get_geometry->fetchAll(PDO::FETCH_ASSOC);
        
        $get_parking->execute(['bctcb2010' => $census_block]);
        $parking = $get_parking->fetchAll(PDO::FETCH_ASSOC);
        
        $all = [];
        
        foreach ($geometry as $g) {
            $order = $g['order_no'];
            unset($g['order_no']);
            $g['geom'] = json_decode($g['geom']);
            $g['signs'] = [];
            $all[$order] = $g;
        }
        
        foreach ($signs as $sign) {
            $order = $sign['order_no'];
            $sign['geom'] = json_decode($sign['geom']);
            unset($sign['order_no']);
            $all[$order]['signs'][] = $sign;
        }
        
        foreach ($parking as $p) {
            array_set((int)$p['length'], $all, $p['order_no'], 'parking_length', $p['day'], $p['period'], $p['type']);
            array_set((int)$p['spaces'], $all, $p['order_no'], 'parking_spaces', $p['day'], $p['period'], $p['type']);
        }
        
        file_put_contents("data/$borough/$tract/$block.json", json_encode($all));

        $block_count++;
        echo "$block_count/" . count($census_blocks) . " blocks\r";
    }
    echo "\n";
    $conn->commit();
}

if (cmd('ungeocoded')) {
    $locations = $conn->query('
        select
            l.order_no,
            l.main_st,
            l.from_st,
            l.to_st,
            l.sos,
            io.error
        from location l
        left join invalid_order io on l.order_no = io.order_no
        left join order_segment os on l.order_no = os.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        where l.order_no in (select order_no from parking group by order_no)
        and bg.bctcb2010 is null and os.bctcb2010 is null
    ')->fetchAll(PDO::FETCH_ASSOC);
    
    $parking = $conn->query('
        select
            p.order_no,
            p.day,
            p.period,
            p.type,
            sum(p.length) length,
            sum(p.spaces) spaces
        from parking p
        left join order_segment os on p.order_no = os.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        where bg.bctcb2010 is null and os.bctcb2010 is null
        group by p.order_no, p.day, p.period, p.type')->fetchAll(PDO::FETCH_ASSOC);
    
    $all = [];
    
    foreach ($locations as $l) {
        $order_no = $l['order_no'];
        unset($l['order_no']);
        $all[$order_no] = $l;
    }
    
    foreach ($parking as $p) {
        array_set((int)$p['length'], $all, $p['order_no'], 'parking_length', $p['day'], $p['period'], $p['type']);
        array_set((int)$p['spaces'], $all, $p['order_no'], 'parking_spaces', $p['day'], $p['period'], $p['type']);
    }
    
    file_put_contents("data/ungeocoded.json", json_encode($all));
}

if (cmd('signs')) {
    $signs = $conn->query('
        select
            mutcd_code,
            max(description) description
        from sign s
        group by mutcd_code')->fetchAll(PDO::FETCH_ASSOC);
    $ret = [];
    foreach ($signs as $sign) {
        $ret[$sign['mutcd_code']] = $sign['description'];
    }
    file_put_contents("data/signs.json", json_encode($ret));
}
