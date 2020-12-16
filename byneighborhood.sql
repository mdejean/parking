select
	ct.borocode, 
	ct.ntaname,
	p.type,
	sum(p.length) lft
from parking p
join order_segment os on os.order_no = p.order_no
left join blockface_geom bg on bg.blockface = os.blockface
join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
join census_tract ct on ct.boroct2010 = cb.borocode || cb.
where p.period = 'night' and p.day = 2
group by ct.borocode, ct.ntaname, p.type
order by ct.borocode, ct.ntaname, lft desc