drop table if exists blockface_hydrant;

create table blockface_hydrant (
    unitid character varying,
    blockface character varying (10),
    distx float8,
    ft_from_curb float8,
    primary key (unitid)
);

insert into blockface_hydrant
select 
    h.unitid,
    bg.blockface,
    MultiLineLocatePoint(bg.geom, h.geom) * ST_Length(bg.geom) distx,
    ST_Distance(bg.geom, h.geom) ft_from_curb
from hydrant h
join blockface_geom bg on bg.blockface = (
    select bg2.blockface 
    from blockface_geom bg2
    where ST_DWithin(bg2.geom, h.geom, 100)
--closer than 1/2 street width to the presumed curb - this catches
--  some non-curbside hydrants but is good enough
    and ST_DWithin(bg2.geom, h.geom, bg2.width/2)
    order by ST_Distance(bg2.geom, h.geom)
    limit 1);


create index ix_blockface_hydrant_blockface on blockface_hydrant (blockface);
