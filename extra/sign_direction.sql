select
-- s.boro,
-- s.distx,
-- s.arrow,
-- bg.azimuth,
-- cosd(bg.azimuth + d.azimuth) * case when os.reversed then -1 else 1 end dir,
-- l.main_st,
-- l.from_st,
-- l.to_st
round(cast(abs(cosd(bg.azimuth + d.azimuth)) as numeric), 1) d,
count(*)
from sign s 
join (values ('N', 0), ('E', 90), ('S', 180), ('W', 270)) d(arrow, azimuth) on d.arrow = s.arrow
left join location l on s.order_no = l.order_no
left join order_segment os on s.order_no = os.order_no 
left join blockface_geom bg on os.blockface = bg.blockface 
left join (
	select 
		street, 
		lblockface blockface 
	from street_segment 
	group by street, lblockface
) ss on os.blockface = ss.blockface
where mutcd_code in (select mutcd_code 
from sign_regulation where arrow = '-->') 
and s.arrow is not null
--and main_st ilike 'bridge st%'
--order by abs(cosd(bg.azimuth + d.azimuth))
group by round(cast(abs(cosd(bg.azimuth + d.azimuth)) as numeric), 1)
limit 100;

