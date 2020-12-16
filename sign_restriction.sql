drop table if exists lookup_restriction_days;

CREATE TABLE public.lookup_restriction_days (
    days character(2) NOT NULL,
    description character varying,
    monday boolean,
    tuesday boolean,
    wednesday boolean,
    thursday boolean,
    friday boolean,
    saturday boolean,
    sunday boolean
);

drop table if exists lookup_restriction_type;

CREATE TABLE public.lookup_restriction_type (
    type character(2),
    description character varying
);


--
-- Data for Name: lookup_restriction_days; Type: TABLE DATA; Schema: public; Owner: user
--

COPY public.lookup_restriction_days (days, description, monday, tuesday, wednesday, thursday, friday, saturday, sunday) FROM stdin;
A 	Anytime	t	t	t	t	t	t	t
XA	Except Saturday	t	t	t	t	t	f	t
XS	Except Sunday	t	t	t	t	t	t	f
D 	Weekdays	t	t	t	t	t	f	f
DW	Monday Tuesday Thursday Friday	t	t	f	t	t	f	f
F 	Friday	f	f	f	f	t	f	f
M 	Monday	t	f	f	f	f	f	f
Sa	Saturday	f	f	f	f	f	t	f
Sc	School Days	t	t	t	t	t	f	f
SS	Weekends	f	f	f	f	f	t	t
Su	Sunday	f	f	f	f	f	f	t
Th	Thursday	f	f	f	t	f	f	f
Tu	Tuesday	f	t	f	f	f	f	f
W 	Wednesday	f	f	t	f	f	f	f
\.


--
-- Data for Name: lookup_restriction_type; Type: TABLE DATA; Schema: public; Owner: user
--

COPY public.lookup_restriction_type (type, description) FROM stdin;
P 	No Parking
S 	No Standing
O 	No Stopping
M 	Metered Parking
A 	Authorized Vehicles
MC	Metered Commerical Parking
C 	Commerical Vehicles
L 	Time Limited Parking
\.


DROP TABLE IF EXISTS sign_restriction;

CREATE TABLE public.sign_restriction
(
  mutcd_code character varying,
  days character(2),
  type character(2),
  start_time time without time zone,
  end_time time without time zone,
  id serial NOT NULL,
  checked boolean,
  extra character varying,
  arrow character(3),
  CONSTRAINT sign_restriction_pkey PRIMARY KEY (id)
);