DROP TABLE if exists parking;

CREATE TABLE parking
(
  order_no character varying(10),
  start numeric,
  length numeric,
  spaces numeric,
  day int,
  period character varying(20),
  type character varying(2),
  primary key (order_no, day, period, start)
);

DROP TABLE if exists parking_stretch;

CREATE TABLE parking_stretch
(
  order_no character varying(10),
  start numeric,
  length numeric,
  spaces numeric,
  mutcd_code character varying,
  primary key (order_no, start, mutcd_code)
);
