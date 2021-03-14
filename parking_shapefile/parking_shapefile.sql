select 
    ps.order_no,
    start,
    length,
    ps.mutcd_code,
    sr.type,
    sr.days,
    sr.start_time,
    sr.end_time,
    sr.extra,
    bg.bctcb2010,
    MultiLineSubstring(bg.geom, start/ST_Length(geom), (start+length)/ST_Length(geom)) geom
from parking_stretch ps
left join sign_regulation sr on ps.mutcd_code = sr.mutcd_code
join order_segment os on ps.order_no = os.order_no
join blockface_geom bg on os.blockface = bg.blockface;
