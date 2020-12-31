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
alter table location add primary key (order_no);

create index ix_street_segment_lblockface on street_segment (lblockface);
create index ix_street_segment_rblockface on street_segment (rblockface);
create index ix_street_segment_streetcode on street_segment (streetcode);
create index ix_street_segment_nodeidfrom on street_segment (nodeidfrom);
create index ix_street_segment_nodeidto on street_segment (nodeidto);

create index ix_census_block_bctcb2010 on census_block (bctcb2010);

create index ix_parking_regulation_order_no on parking_regulation(order_no);

create index ix_hydrant_unitid on hydrant(unitid);
