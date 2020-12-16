update import_sign set 
	"Sign_description" = "Sign_description" || ',' || "SR_Mutcd_Code",
	"SR_Mutcd_Code" = h,
	h = i,
	i = null
where i is not null;

update import_sign set
	"Sign_description" = "Sign_description" || ',' || "SR_Mutcd_Code",
	"SR_Mutcd_Code" = h,
	h = null
where h is not null;

drop table if exists sign;

create table sign (
	boro character,
	order_no character varying,
	seq numeric,
	distx numeric,
	arrow character varying,
	description character varying,
	mutcd_code character varying,
	primary key (order_no, seq)
);

insert into sign 
select 
	"SRP_Boro",
	trim("SRP_Order"),
	"SRP_Seq",
	"SR_Distx",
	trim("SR_Arrow"),
	trim("Sign_description"),
	trim("SR_Mutcd_Code")
from import_sign;

drop table import_sign;

update location set order_no = trim(order_no);