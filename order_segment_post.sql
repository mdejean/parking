-- delete some rows in an attempt to make order_segment a one-to-one mapping

delete from order_segment os1
using order_segment os2
where os1.order_no > os2.order_no
and os1.blockface = os2.blockface;

-- attempt to find blockface for signs which have been geocoded by DOT
insert into order_segment (
    order_no, 
    bctcb2010,
    blockface,
    reversed
) select 
    order_no, 
    cb.bctcb2010,
    nearest.blockface,
    nearest.reversed
from (
    select 
        order_no,
        ST_Transform(ST_Union(geom), 2263) geom 
    from parking_regulation
    group by order_no
) pr
--closest census block 
-- st_distance should be 0 for inside, but some slip through the cracks
left join lateral (
    select 
        bctcb2010 
    from census_block b 
    where ST_DWithin(b.geom, pr.geom, 100) 
    order by ST_Distance(b.geom, pr.geom) 
    limit 1
) cb on true
--first sign on block - we're just going to guess that the first sign
-- is closer to the 'beginning' of the block because distx is not 
-- populated in parking_regulation
left join lateral (
    select
        ST_Transform(pr2.geom, 2263) geom
    from parking_regulation pr2
    where pr.order_no = pr2.order_no
    order by pr2.seq
    limit 1
) first_sign on true
left join lateral (
    select
        bg.blockface,
        (select avg(ST_Distance(bg.geom, part.geom)) from ST_Dump(pr.geom) part) avg_dist,
        case 
            when MultiLineLocatePoint(bg.geom, first_sign.geom) > 0.5 then true
            else false
        end reversed
    from blockface_geom bg
    where ST_DWithin(bg.geom, first_sign.geom, 100)
    -- skip blockfaces which were geocoded in previous step
    and bg.blockface not in (
        select blockface from order_segment
    )
    order by avg_dist
    limit 1
) nearest on true
-- skip orders which were geocoded in previous step
where order_no not in (
    select order_no from order_segment
);