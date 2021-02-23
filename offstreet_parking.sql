drop table if exists offstreet_parking;

create table offstreet_parking (
    bbl character varying,
    bctcb2010 character varying,
    spaces int,
    area int,
    source char(1),
    primary key (bbl)
);

SELECT AddGeometryColumn ('public','offstreet_parking','geom',2263,'POINT',2);

create index ix_offstreet_parking_bctcb2010 on offstreet_parking (bctcb2010);

-- G: garagearea only

insert into offstreet_parking (
    bbl,
    spaces,
    area,
    source,
    geom
) select
    bbl,
    null,
    garagearea,
    'G',
    ST_Centroid(geom)
from pluto p
where garagearea > 0
on conflict (bbl) do update
set
    area = excluded.area, 
    source = excluded.source;

-- H: assume that 1/2/3 family homes with a garage 
--    or extension and garage have one parking space

insert into offstreet_parking (
    bbl,
    spaces,
    area,
    source,
    geom
) select
    bbl,
    1,
    null,
    'H',
    ST_Centroid(geom)
from pluto p
where unitsres between 1 and 3
and ext in ('G', 'EG')
on conflict (bbl) do update
set 
    spaces = excluded.spaces, 
    source = excluded.source;

-- D: DCA licensed public parking facilities use licensed capacity

insert into offstreet_parking (
    bbl,
    spaces,
    area,
    source,
    geom
) select
    coalesce(g.bbl, closest.bbl) bbl,
    max(spaces),
    null,
    'D',
    max(geom)
from dca_garage g
left join lateral (
    select bbl
    from pluto p
    where ST_DWithin(g.geom, p.geom, 1000)
    order by ST_Distance(g.geom, p.geom)
    limit 1
) closest on true
where coalesce(g.bbl, closest.bbl) is not null
group by coalesce(g.bbl, closest.bbl)
on conflict (bbl) do update
set 
    spaces = excluded.spaces, 
    source = excluded.source;

-- set bctcb2010

update offstreet_parking op
set bctcb2010 = (
    select 
        bctcb2010 
    from census_block cb 
    where ST_Within(op.geom, cb.geom)
); 
