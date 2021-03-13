create or replace function MultiLineLocatePoint(line geometry, point geometry) returns float8 as $$
select 
    (base + extra) / ST_Length(line)
from (
    select 
        sum(ST_Length(l.geom)) over (order by l.path) - ST_Length(l.geom) base,
        ST_LineLocatePoint(l.geom, point) * ST_Length(l.geom) extra,
        ST_Distance(l.geom, point) dist
    from ST_Dump(line) l
) points
order by dist
limit 1;
$$ language SQL;

create or replace function MultiLineInterpolatePoint(line geometry, location float8) returns geometry as $$
select 
    ST_LineInterpolatePoint(parts.geom, (ST_Length(line) * location - segment_start) / segment_length)
from (
    select 
        sum(ST_Length(l.geom)) over (order by l.path) - ST_Length(l.geom) segment_start,
        sum(ST_Length(l.geom)) over (order by l.path) segment_end,
        ST_Length(l.geom) + 0.001 segment_length,
        l.geom geom
    from ST_Dump(line) l
) parts
where ST_Length(line) * location between segment_start and segment_end 
limit 1;
$$ language SQL;

create or replace function MultiLineSubstring(line geometry, startfraction float8, endfraction float8) returns geometry as $$
select 
    ST_Collect(
        ST_LineSubstring(
            s.geom, 
            case 
                when seg_start > startfraction then 0 
                else (startfraction - seg_start)/(seg_end - seg_start)
            end,
            case
                when seg_end < endfraction then 1
                else 1 - (seg_end - endfraction)/(seg_end - seg_start)
            end
        )
        order by s.path
    )
from (
    select 
        l.geom,
        l.path,
        (sum(ST_Length(l.geom)) over (order by l.path) - ST_Length(l.geom))/ST_Length(line) seg_start,
        (sum(ST_Length(l.geom)) over (order by l.path))/ST_Length(line) seg_end
    from ST_Dump(line) l
) s
where seg_end > startfraction 
and seg_start < endfraction;
$$ language SQL;
