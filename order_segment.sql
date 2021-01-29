DROP TABLE IF EXISTS public.order_segment;

CREATE TABLE public.order_segment
(
  order_no character varying(10) NOT NULL,
  blockface character varying(10),
  reversed boolean,
  bctcb2010 character varying(12),
  primary key (order_no)
);
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
    order by avg_dist
    limit 1
) nearest on true;

CREATE TABLE public.invalid_order
(
  order_no character varying(10) NOT NULL,
  error character varying(400),
  PRIMARY KEY (order_no)
);

create index ix_order_segment_bctcb2010 on order_segment (bctcb2010);
create index ix_order_segment_blockface on order_segment (blockface);