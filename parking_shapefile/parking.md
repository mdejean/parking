A shapefile containing each stretch of signed curbside space in NYC and the parking regulations that apply to it.

--

# order_no

DOT's curb identifier

# start

Position along the curb of the start of the segment, in feet

# length

Length of the segment in feet

# mutcd_code

Sign type, you can find these in small letters at the bottom of each sign made by NYCDOT, for example R1-1 is a stop sign.

# type

Type of regulation

type | description
---|----
P  | No Parking
S  | No Standing
O  | No Stopping
M  | Metered Parking
A  | Authorized Vehicles
MC | Metered Commerical Parking
C  | Commerical Vehicles
L  | Time Limited Parking

# days

Days that the parking regulation is in effect

days | description | monday | tuesday | wednesday | thursday | friday | saturday | sunday
---|----------|---|---|---|---|---|---|---
A  | All Days | t | t | t | t | t | t | t
M  | Monday | t | f | f | f | f | f | f
Tu | Tuesday | f | t | f | f | f | f | f
W  | Wednesday | f | f | t | f | f | f | f
Th | Thursday | f | f | f | t | f | f | f
F  | Friday | f | f | f | f | t | f | f
Sa | Saturday | f | f | f | f | f | t | f
Su | Sunday | f | f | f | f | f | f | t
XA | Except Saturday | t | t | t | t | t | f | t
XS | Except Sunday | t | t | t | t | t | t | f
D  | Weekdays | t | t | t | t | t | f | f
SS | Weekends | f | f | f | f | f | t | t
DW | Monday Tuesday Thursday Friday | t | t | f | t | t | f | f
Sc | School Days | t | t | t | t | t | f | f

# start_time

Time each day that the parking regulation takes effect

# end_time

Time each day that the parking regulation ends

# extra

Other uninterpreted text from the sign

# bctcb2010

Borough code, census tract, census block that the segment is located in