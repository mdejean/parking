DROP TABLE IF EXISTS public.order_segment;

CREATE TABLE public.order_segment
(
  order_no character varying(10) NOT NULL,
  blockface character varying(10),
  reversed boolean,
  bctcb2010 character varying(12),
  primary key (order_no)
);

-- set bctcb2010 to the nearest census block within 100 feet 
--  for signs which have been geocoded by DOT
insert into order_segment (
    order_no, 
    bctcb2010
) select 
    order_no, 
    (
        select 
            bctcb2010 
        from census_block b 
        where ST_DWithin(b.geom, pr.geom, 100) 
        order by ST_Distance(b.geom, pr.geom) 
        limit 1
    ) bctcb2010 
from (
    select 
        order_no, 
        ST_Transform(ST_Union(geom), 2263) geom 
    from parking_regulation 
    group by order_no
) pr;

CREATE TABLE public.invalid_order
(
  order_no character varying(10) NOT NULL,
  error character varying(400),
  PRIMARY KEY (order_no)
);

create index ix_order_segment_bctcb2010 on order_segment (bctcb2010);
create index ix_order_segment_blockface on order_segment (blockface);