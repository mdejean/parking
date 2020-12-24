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
