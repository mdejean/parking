DROP TABLE if exists blockface_geom;

CREATE TABLE blockface_geom
(
    blockface character varying(10),
    sos character(1) NOT NULL,
    bctcb2010 character varying(12),
    width numeric,
    azimuth float,
    parking_lanes int,
    primary key (blockface, sos)
);

SELECT AddGeometryColumn ('public','blockface_geom','geom',2263,'MULTILINESTRING',2);


with ss as (
        select
            streetcode,
            lblockface, rblockface,
            lboro, rboro,
            lboro || LPAD(lct2010, 4, '0') || LPAD(coalesce(lct2010suf,''), 2, '0') || LPAD(lcb2010, 4, '0') || coalesce(lcb2010suf,'') lbctcb2010,
            rboro || LPAD(rct2010, 4, '0') || LPAD(coalesce(rct2010suf,''), 2, '0') || LPAD(rcb2010, 4, '0') || coalesce(rcb2010suf,'') rbctcb2010,
            streetwidt width,
            xfrom, yfrom,
            xto, yto,
            facecode,
            seqnum,
            nodeidfrom,
            nodeidto,
            cast(number_par as int) parking_lanes,
            geom
        from street_segment
        where rb_layer in ('R', 'B') --exclude generic segments
        and specaddr is null --exclude alternate address segments
)
insert into blockface_geom (
    blockface,
    sos,
    azimuth,
    bctcb2010,
    width,
    parking_lanes,
    geom
) select
    blockface,
    sos,
    -- the 'manhattan shift' considers manhattans avenues to be exactly north-south
    90 - atan2d(dy, dx) - case when boro = '1' then 30 else 0 end azimuth,
    bctcb2010,
    width,
    parking_lanes,
    ST_Multi(
        -- offset the street centerline to the curb
        ST_OffsetCurve(
            -- cut the ends off the street where the street centerlines connect 
            -- in the intersection
            MultiLineSubstring(
                geom, 
                case 
                    when from_st_width > ST_Length(geom) then 0 
                    else coalesce(from_st_width/2, 0)/ST_Length(geom)
                end, 
                case 
                    when to_st_width > ST_Length(geom) then 1
                    else 1 - coalesce(to_st_width/2, 0)/ST_Length(geom)
                end
            ),
            case 
                when sos = 'l' then width/2 
                when sos = 'r' then -width/2 
            end
        )
    ) geom
from (
    select
        lblockface as blockface,
        min(streetcode) streetcode,
        'l' as sos,
        max(lboro) boro,
        max(lbctcb2010) bctcb2010,
        min(width) width,
--option 1: orientation of this block
--          sum(xto - xfrom) dx,
--          sum(yto - yfrom) dy,
        ST_Collect(geom order by facecode, seqnum) geom,
        right(min(seqnum || nodeidfrom), 7) nodeidfrom,
        right(max(seqnum || nodeidto), 7) nodeidto,
        max(parking_lanes) parking_lanes
    from ss
    where lblockface is not null
    group by lblockface
    union all
    select
        rblockface as blockface,
        min(streetcode) streetcode,
        'r' as sos,
        max(rboro) boro,
        max(rbctcb2010) as bctcb2010,
        min(width) width,
--          sum(xto - xfrom) dx,
--          sum(yto - yfrom) dy,
        ST_Collect(geom order by facecode, seqnum) geom,
        right(min(seqnum || nodeidfrom), 7) nodeidfrom,
        right(max(seqnum || nodeidto), 7) nodeidto,
        max(parking_lanes) parking_lanes
    from ss
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
left join lateral (
select max(streetwidt) from_st_width 
from street_segment s1 
where b.streetcode != s1.streetcode 
and (s1.nodeidfrom = b.nodeidfrom 
    or s1.nodeidto = b.nodeidfrom)
limit 1) x1 on true
left join lateral (
select max(streetwidt) to_st_width 
from street_segment s2 
where b.streetcode != s2.streetcode 
and (s2.nodeidfrom = b.nodeidto 
    or s2.nodeidto = b.nodeidto)
limit 1) x2 on true;

--there are roughly 1000 blockfaces that are on both the left and right side of the street (????).
--there are 18 blockfaces with no geometry (???)
--there is one blockface with empty geometry

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
) or geom is null
or ST_IsEmpty(geom);

-- negative ST_OffsetCurve (right side of street) reverses lines, so re-reverse them
update blockface_geom
set geom = ST_Reverse(geom)
where sos = 'r';

create index ix_blockface_geom_bctcb2010 on blockface_geom(bctcb2010);
create index ix_blockface_geom_geom on blockface_geom using gist(geom);
