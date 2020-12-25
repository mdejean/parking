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
