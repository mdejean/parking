DROP TABLE if exists blockface_geom;

CREATE TABLE blockface_geom
(
  blockface character varying(10),
  sos character(1) NOT NULL,
  bctcb2010 character varying(12),
  geom geometry,
  width numeric,
  azimuth float,
  primary key (blockface, sos)
);

insert into blockface_geom (
	blockface,
	sos,
	azimuth,
	bctcb2010,
	geom,
	width
) select
	blockface,
	sos,	
	azimuth,
	bctcb2010,
	ST_SetSRID(geom,102718),
	width
from (
	select 
		b.*, 
		90 - atan2d(dy, dx) - case when boro = '1' then 30 else 0 end azimuth
	from (
		select
			lblockface as blockface,
			min(streetcode) streetcode,
			'l' as sos,
			max(lboro) boro,
            max(lboro || LPAD(lct2010, 4, '0') || LPAD(coalesce(lct2010suf,''), 2, '0') || LPAD(lcb2010, 4, '0') || coalesce(lcb2010suf,'')) as bctcb2010,
			max(streetwidt) width,
--option 1: orientation of this block
-- 			sum(xto - xfrom) dx,
-- 			sum(yto - yfrom) dy,
			ST_Union(ST_OffsetCurve(geom, streetwidt)) geom
		from street_segment
		where lblockface is not null
		group by lblockface
		union all
		select
			rblockface as blockface,
			min(streetcode) streetcode,
			'r' as sos,
			max(rboro) boro,
            max(rboro || LPAD(rct2010, 4, '0') || LPAD(coalesce(rct2010suf,''), 2, '0') || LPAD(rcb2010, 4, '0') || coalesce(rcb2010suf,'')) as bctcb2010,
			max(streetwidt) width,
-- 			sum(xto - xfrom) dx,
-- 			sum(yto - yfrom) dy,
			ST_Union(ST_OffsetCurve(geom, -streetwidt)) geom
		from street_segment
		where rblockface is not null
		group by rblockface
	) b
--option 2: orientation of the entire street
	join (select
		streetcode,
		sum(xto - xfrom) dx,
		sum(yto - yfrom) dy
		from street_segment
		group by streetcode
	) s on b.streetcode = s.streetcode
) s;


--there are roughly 1000 blockfaces that are on both the left and right side of the street (????).
--there are 18 blockfaces with no geometry (???)

delete from blockface_geom 
where (
    sos = 'r' 
    and blockface in (
        select 
            blockface 
        from blockface_geom 
        where sos = 'l' 
        group by blockface
    )
) or geom is null;

create index ix_blockface_geom_bctcb2010 on blockface_geom(bctcb2010);