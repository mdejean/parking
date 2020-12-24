<?PHP


$conn = new PDO('pgsql:dbname=parking');

function cmd($k) {
    global $argv;
    return isset($_GET[$k]) or (isset($argv[1]) and $argv[1] == $k);
}

if (cmd('order_segment')) {
    
    $orders = $conn->query('
    select 
        order_no, 
        boro, 
        main_st as on_street, 
        from_st as from_street, 
        to_st as to_street, 
        sos 
    from location l') or trigger_error($conn->errorInfo(), E_USER_ERROR);
    $rows = $orders->fetchAll(PDO::FETCH_ASSOC);
    $orders->closeCursor();

    $boros = ['' => 0, 'M' => 1, 'B' => 2, 'K' => 3, 'Q' => 4, 'S' => 5];

    $order_segment = [];
    $valid = 0;
    $invalid = 0;

    $insert_os = $conn->prepare('
    insert into order_segment (order_no, blockface, reversed) 
    values (:order_no, :blockface, :reversed)
    on conflict (order_no) do update set
    blockface = excluded.blockface,
    reversed = excluded.reversed');
    $insert_err = $conn->prepare('
    insert into invalid_order ( order_no, error) 
    values (:order_no, :error)');
    
    $conn->beginTransaction();
    
    foreach ($rows as $row) {
        $cmd = './blockface ' . escapeshellarg($boros[$row['boro']])
                . ' ' . escapeshellarg($row['on_street']) 
                . ' ' . escapeshellarg($row['from_street']) 
                . ' ' . escapeshellarg($row['to_street'])
                . ' ' . escapeshellarg($row['sos']);
        $out = exec($cmd);
        if (strpos($out, "{") === 0) {
            $invalid++;
            $insert_err->execute(['order_no' => $row['order_no'], 'error' => $out]) or
                trigger_error(print_r($insert_err->errorInfo(), true), E_USER_ERROR);;
            $insert_err->closeCursor();
        } else {
            $valid++;
            $reversed = 0;
            if (strpos($out, "-") === 0) {
                $out = substr($out, 1);
                $reversed = 1;
            }

            $insert_os->execute(['order_no' => $row['order_no'], 'blockface' => $out, 'reversed' => $reversed]) or
                trigger_error(print_r($insert_os->errorInfo(), true), E_USER_ERROR);
            $insert_os->closeCursor();
        }
        echo "\r$valid valid and $invalid invalid segments";
    }
    echo "\n";
    $conn->commit();
}

if (cmd('sign_types')) {
    header('Content-Type: application/json');
    
    $result = $conn->query('
    select 
        sr.id as rowid,
        s.mutcd_code,
        s.description,
        sr.days,
        sr.type,
        sr.start_time,
        sr.end_time,
        sr.checked
    from (
        select
            mutcd_code,
            max(description) description
        from sign s
        group by mutcd_code) s
    left join sign_regulation sr on s.mutcd_code = sr.mutcd_code
    order by s.mutcd_code, sr.id') or trigger_error(print_r($conn->errorInfo(), true), E_USER_ERROR);
    
    echo json_encode($result->fetchAll(PDO::FETCH_ASSOC));
}

if (cmd('lookup')) {
    header('Content-Type: application/json');
    $result = $conn->query($_GET['t'] == 'days' 
        ? 'select days, description from lookup_regulation_days' 
        : 'select type, description from lookup_regulation_type'
    ) or trigger_error(print_r($conn->errorInfo(), true), E_USER_ERROR);

    echo json_encode(array_reduce(
        $result->fetchAll(PDO::FETCH_NUM),
        function($c, $i) {$c[$i[0]] = $i[1]; return $c;}, 
        []
    ));
}

if (cmd('add_sign_regulation')) {
$columns = [
        'days',
        'type',
        'start_time', 
        'end_time',
        'checked'
    ];

    $q = null;
    if (!empty($_POST['rowid'])) {
        $q = $conn->prepare('update sign_regulation set ' 
            . implode(',', array_map(function($v) {return $v . ' = :' . $v;}, $columns))
            . ' where id = :id');
        //renamed from id to rowid because of conflict with js property
        $q->bindValue(':id', $_POST['rowid']);
    } else {
        $q = $conn->prepare(
            'insert into sign_regulation (' 
            . implode(',', $columns) 
            . ') values (' 
            . implode(',', array_map(function($v) {return ':' . $v;}, $columns)) 
            . ')');
    }
    
    foreach ($columns as $column) {
        if (!empty($_POST[$column])) {
            if ($column == 'start_time' or $column == 'end_time') {
                $q->bindValue(':' . $column, (new DateTime($_POST[$column]))->format('H:i:s'));
            } else {
                $q->bindValue(':' . $column, $_POST[$column]);
            }
        } else {
            $q->bindValue(':' . $column, null);
        }
    }

    if (!$q->execute()) {
        http_response_code(500);
    }
}

function any($a) {
    if (!is_array($a)) return (bool)$a;
    foreach ($a as $v) {
        if ($v) {
            return true;
        }
    }
    return false;
}

function all($a) {
    if (!is_array($a)) return (bool)$a;
    foreach ($a as $v) {
        if (!$v) {
            return false;
        }
    }
    return true;
}

$lookup_days = [ //in db but i don't want to query
    "A"  => [true ,true ,true ,true ,true ,true ,true ],
    "D"  => [true ,true ,true ,true ,true ,false,false],
    "DW" => [true ,true ,false,true ,true ,false,false],
    "F"  => [false,false,false,false,true ,false,false],
    "M"  => [true ,false,false,false,false,false,false],
    "Sa" => [false,false,false,false,false,true ,false],
    "Sc" => [true ,true ,true ,true ,true ,false,false],
    "SS" => [false,false,false,false,false,true ,true ],
    "Su" => [false,false,false,false,false,false,true ],
    "Th" => [false,false,false,true ,false,false,false],
    "Tu" => [false,true ,false,false,false,false,false],
    "W"  => [false,false,true ,false,false,false,false],
    "XA" => [true ,true ,true ,true ,true ,false,true ],
    "XS" => [true ,true ,true ,true ,true ,true ,false],
];


class Regulation {
    public $days;
    public $type;
    public $times;
    public $extra;
    public $arrow;
    public $mutcd_code;
    
    function combine($reg2) {
        $ret = null;

        //days <> days, left regulation must be complete, right can keep same regulation type but not time
        //don't clobber more specific days with School Days (but hang on to it if we have nothing else)
        //e.g. PS-180C: 1 HMP MONDAY-FRIDAY 8:30AM-4PM [[here]] SATURDAY 8:30AM-7PM <->
        if (!empty($reg2->days) and ($reg2->days != 'Sc' or empty($this->days))) {
            if (!empty($this->days) and $this->days != 'Sc') {
                $ret = clone $this;
                $this->times = null;
                $this->extra = null;
            }
            $this->days = $reg2->days;
        }

        //times <> times, left regulation must be complete, right can keep same days and regulation type
        //e.g. PS-176C: 2 HMP MONDAY-FRIDAY 8AM-4PM [[here]] 7PM-10PM SATURDAY 8AM-10PM <->
        if (!empty($reg2->times)) {
            if (!empty($this->times)) {
                $ret = clone $this;
                $this->extra = null;
            }
            $this->times = $reg2->times;
        }
        
        
        //reg <> reg
        //left regulation must be complete, right keeps nothing if the regulations don't combine
        //e.g. 3 HOUR METERED PARKING [[here]] COMMERCIAL VEHICLES ONLY OTHERS NO STANDING MONDAY-FRIDAY 8AM-6PM <-> [[here]] 2 HOUR METERED PARKING SATURDAY 8AM-6PM <-> 
        
        $regreg = [
        //v left reg            right reg >
            'A' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //e.g.   PS-100D       SP-2A*      PS-413D*      SP-807CA*
            'O' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //e.g.   SP-6A*                    SP-7AA
            'S' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //e.g.   SP-1013B                                                          PS-101F      PS-49F
            'P' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //e.g.   SP-307C
            'MC'=> [ 'A' => null ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //                                                                         PS-104F
            'M' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => 'MC', 'L' => null],
            //e.g.   PS-140C                                                           PS-335C      PS-101F
            'C' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => 'C' , 'L' => null],
            //e.g.   PS-206FA*                 PS-550E*                                PS-104F      PS-109E
            'L' => [ 'A' => 'A'  ,'O' => null, 'S' => null, 'P' => null, 'MC' => null, 'M' => null, 'C' => null, 'L' => null],
            //no examples
        ];
        
        if (!empty($reg2->type)) {
            if (!empty($this->type)) {
                if (!empty($regreg[$this->type][$reg2->type])) {
                    $this->type = $regreg[$this->type][$reg2->type];
                } else {
                    $ret = clone $this;
                    $this->times = null;
                    $this->days = null;
                    $this->extra = null;
                    $this->type = $reg2->type;
                }
            } else {
                $this->type = $reg2->type;
            }
        }
        
        if (!empty($reg2->extra)) {
            $this->extra .= $reg2->extra;
        }
        
        if (!empty($reg2->arrow) and empty($this->arrow)) {
            $this->arrow = $reg2->arrow;
        }
        return $ret;
    }
    
    public function daysAsString() {
        global $lookup_days;
        if (!is_array($this->days)) {
            return $this->days;
        } else {
            if ($r = array_search($this->days, $lookup_days)) {
                return $r;
            } else {
                $r = '';
                for ($i=0;$i<7;$i++) {
                    if ($this->days[$i]) {
                        $r .= ['M','Tu', 'W','Th', 'F', 'Sa', 'Su'][$i];
                    }
                }
                return $r;
            }
        }
    }
    
    public function valid() {
        return !empty($this->type) and !empty($this->days) and !empty($this->times);
    }
    
    public function equals($other) {
        return  $this->type == $other->type
            and $this->days == $other->days
            and $this->times == $other->times;
    }
    
    public function applies($day, $time) {
        global $lookup_days;
        if (!$this->valid()) return false;
        
        if ($lookup_days[$this->days][$day]) {
            if ($this->times[0] == 0 and $this->times[1] == 0) {
                return true;
            } elseif ($this->times[0] < $this->times[1]) {
                return $time >= $this->times[0] and $time < $this->times[1];
            } else {
                return $time >= $this->times[0] or $time < $this->times[1];
            }
        }
        
        return false;
    }
    
    public function __toString() {
        if ($this->valid()) {
            return $this->type . ' ' . $this->daysAsString() . ' ' . gmstrftime('%H:%M', $this->times[0]) . '-' . gmstrftime('%H:%M', $this->times[1]);
        } else {
            return 'invalid';
        }
    }
    
    public function insert($conn) {
        global $lookup_days;
        
        $columns = [
                'mutcd_code',
                'type',
                'days',
                'start_time', 
                'end_time',
                'extra',
                'arrow',
                'checked'
        ];

        $q = $conn->prepare(
            'insert into sign_regulation (' 
            . implode(',', $columns) 
            . ') values (' 
            . implode(',', array_map(function($v) {return ':' . $v;}, $columns)) 
            . ')');
        
        $params = [
            'mutcd_code' => $this->mutcd_code,
            'type'       => $this->type,
            'extra' => $this->extra,
            'arrow' => $this->arrow,
            'checked' => $this->valid() ? 'false' : null,
            'start_time' => empty($this->times) ? null : gmstrftime('%H:%M', $this->times[0]),
            'end_time' => empty($this->times) ? null : gmstrftime('%H:%M', $this->times[1]),
        ];
        
        $days = null;
        if (is_array($this->days)) {
            //look for an alias
            $days = array_search($this->days, $lookup_days);
            
            //otherwise insert multiple rows
            if (empty($days)) {
                for ($i=0;$i<7;$i++) {
                    if ($this->days[$i]) {
                        $params['days'] = ['M','Tu', 'W','Th', 'F', 'Sa', 'Su'][$i];
                        if (!$q->execute($params)) {
                            print_r($q->errorInfo());
                        }
                    }
                }
                
                return;
            }
        } else {
            $days = $this->days;
        }
        $params['days'] =  $days;
        if (!$q->execute($params)) {
            print_r($q->errorInfo());
        }
    }
}

function parse_time($s) {
    $old = date_default_timezone_get();
    date_default_timezone_set('UTC');
    $r = strtotime($s, 0);
    date_default_timezone_set($old);
    return $r;
}

function interpret_sign($mutcd_code, $s) {

    $s = preg_replace('/\((SU|US)[^)]*(\)|$) */', '', $s);
    $s = preg_replace('/SUPERSED ES.*$/', '', $s);
    $s = preg_replace('/\(REPLACED BY.*$/', '', $s);
    $s = preg_replace('/\(FOLDING SIGN.*$/', '', $s);
    $s = preg_replace('/\(FOLDING SIGN.*$/', '', $s);
    $s = preg_replace('/\(FOR 8.*$/', '', $s);
    $s = preg_replace('/\(SEE R7-.*$/', '', $s);
    $s = preg_replace('/SPECIAL NIGHT REGULATION (\(MOON & STARS (SYMBOLS?)?\)|\/?)?/', '', $s);
    $s = preg_replace('/10A M/', '10AM', $s);
    $s = preg_replace('/THU RSDAY/', 'THURSDAY', $s);
    $s = preg_replace('/SATU DAY/', 'SATURDAY', $s);
    $s = preg_replace('/MIDNIGH T/', 'MIDNIGHT', $s);
    $s = preg_replace('/\((SANITATION )?BROOM+ SYMBOL\)( *W\/)? */', '', $s);
    $s = preg_replace('/\(MOON *(\/|&) *STARS SYMBOLS\) */', '', $s);
    $s = preg_replace('/MOON & STARS \(SYMB ?OLS\) */', '', $s);

    $handle_time = function (&$s) {
        $time_regex = '((\\d\\d?)(:(\\d\\d))? *([AP]M)?|NOON|MIDNIGHT)';
        if (preg_match("/^$time_regex *(-|TO|TO-) *$time_regex */", $s, $matches)) {
            $s = substr($s, strlen($matches[0]));
            $reg = new Regulation();
            $reg->times = [parse_time($matches[1]), parse_time($matches[7])];
            return $reg;
        } else {
            return false;
        }
    };

    $handle_days = function (&$s) {
        $found = [false, false, false, false, false, false, false];
        $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        for ($i = 0; $i < count($days); $i++) {
            if (preg_match("/^EXC?(EPT)?.? *" . $days[$i] . '[\w]* */', $s, $matches)) {
                $s = substr($s, strlen($matches[0]));
                for ($j = 0; $j < 7; $j++) {
                    if ($i != $j) {
                        $found[$j] = true;
                    }
                }
                break;
            }
            
            for ($j = 0; $j < 7; $j++) {
                if (preg_match("/^" . $days[$i] . "[A-Z]* *(THRU|-) *" . $days[$j] . "[A-Z]*[.]? */", $s, $matches)) {
                    $s = substr($s, strlen($matches[0]));
                    for ($k = $i; $k != $j; $k = ($k + 1) % 7) {
                        $found[$k] = true;
                    }
                    $found[$k] = true;
                    break;
                }
            }
            
            if (preg_match("/^" . $days[$i] . "[A-Z]*\.? *&? */", $s, $matches)) {
                $s = substr($s, strlen($matches[0]));
                $found[$i] = true;

                //start over (so days can be found in any order)
                $i = -1;
            }
        }
        
        if (any($found)) {
            $reg = new Regulation();
            $reg->days = $found;
            return $reg;
        } else {
            return false;
        }
    };
    
    $regexp_token = function ($re, $type, $days, $f = null) {
        return function(&$s) use ($re, $type, $days, $f) {
            if (preg_match($re, $s, $matches)) {
                $s = substr($s, strlen($matches[0]));
                $reg = new Regulation();

                if (isset($f)) {
                    return $f($matches);
                } elseif (!empty($type)) {
                    $reg->type = $type;
                    if ($type == 'A') {
                        $reg->extra = $matches[0];
                    }
                } elseif(!empty($days)) {
                    $reg->days = $days;
                } else {
                    $reg->extra = $matches[0];
                }
                return $reg;
            } else {
                return false;
            }
        };
    };
    
    $funcs = [
        $regexp_token('/^NO (PARKING|STANDING|STOPPING) (ANYTIME )?EXCEPT */'      , null, null), //non-prescriptive signs 
        $regexp_token('/^OTHERS NO (PARKING|STANDING|STOPPING) *(ANYTIME )?/'      , null, null), // ''
        $regexp_token('/^([0-9\/]+) *H(OU)?R\.? *(METERED|MUNI-METER) *PARKING */' , 'M' , null),
        $regexp_token('/^METERED *PARKING ([0-9\/]+) *H(OU)?R LIMIT*/'             , 'M' , null),
        $regexp_token('/^([0-9\/]+) *HMP */'                                       , 'M' , null),
        $regexp_token('/^([0-9\/]+) *((H(OU)?R|MIN(UTE)?)\.? *PARKING|HP ) */'     , 'L' , null),
        $regexp_token('/^(NO PARKING|N\/P) */'                                     , 'P' , null),
        $regexp_token('/^(NO STANDING|N\/S) */'                                    , 'S' , null),
        $regexp_token('/^NO STOPPING */'                                           , 'O' , null),
        $regexp_token('/^BUSES ONLY */'                                            , 'A' , null),
        $regexp_token('/^MOTORCYCLE PARKING ONLY */'                               , 'A' , null),
        $regexp_token('/^CO *MM(ERCIAL| VEHICLES)( VEHICLES)?( ONLY)? */'          , 'C' , null),
        $regexp_token('/^TRUCK?S?( LOADING)? ONLY */      '                        , 'C' , null),
        $regexp_token('/^STAR \(SYMBOL\) */'                                       , 'A' , null),
        $regexp_token('/^TRUCK \(SYMBOL\) */'                                      , 'C' , null),
        $regexp_token('/^FHV \(SYMBOL\) */'                                        , 'A' , null),
        $regexp_token('/^(FHV|FOR(-| )HIRE VEHICLES?) ONLY */'                     , 'A' , null),
        $regexp_token('/^CROSS \(SYMBOL\) */'                                      , 'A' , null),
        $regexp_token('/^BUS \(?SYMBOL\)? */'                                      , 'A' , null),
        $regexp_token('/^BUS LAYOVER (AREA|ZONE|ONLY) */'                          , 'A' , null),
        $regexp_token('/^PRESS \(SYMBOL\) */'                                      , 'A' , null),
        $regexp_token('/^TAXI( HAIL(ING)?)?( \(SYMBOL\))?( STAND)? */'             , 'A' , null),
        $regexp_token('/^(AVO|AUTHORIZED VEHICLES( ONLY)?) */'                     , 'A' , null), 
        $regexp_token('/^(US POSTAL SERVICE) */'                                   , 'A' , null), 
        $regexp_token('/^CAR \(SYMBOL\) CARSHARE PARKING ONLY */'                  , 'A' , null), 
        $regexp_token('/^(-+ *>|(W\/ )?\(?(SINGLE )?ARROW\)?) */'                  , null, null, 
            function($m) {
                $r = new Regulation(); 
                $r->arrow = '-->'; 
                return $r;
            }),
        $regexp_token('/^(< *-+ *>|(W\/ )?\(?DOUBLE ARROW\)?) */'                  , null, null, 
            function($m) {
                $r = new Regulation();
                $r->arrow = '<->'; return $r;
            }),
        $regexp_token('/^(ALL DAYS|INCLUDING SUNDAY) */'                           , null, 'A' ),
        $regexp_token('/^(M-F) */'                                                 , null, 'D' ),
        $regexp_token('/^(SCHOOL DAYS) */'                                         , null, 'Sc'),
        $regexp_token('/^ALL DAY +/'                                               , null, null, 
            function($m) {
                $r = new Regulation();
                $r->times = [0, 0];
                return $r;
            }),
        $regexp_token('/^ANYTIME */'                                               , null, null, 
            function($m) {
                $r = new Regulation();
                $r->days = 'A';
                $r->times = [0, 0];
                return $r;
            }),
        $handle_time,
        $handle_days,
        $regexp_token('/^[\S]+\s*/'                                                , null, null, 
            function($m) {
                $r = new Regulation();
                $r->extra = $m[0];
                return $r;
            }),
    ];
    
    $regs = [];
    $left = new Regulation();
    $left->mutcd_code = $mutcd_code;
    
    while (!empty($s)) {
        foreach ($funcs as $f) {
            $s = ltrim($s);
            if ($right = $f($s)) {
                $complete = $left->combine($right);
                
                if ($complete) {
                    $regs[] = $complete;
                }
                
                break;
            }
        }
    }
    
    $regs[] = $left;
    
    //propagate days and arrows backwards where needed.
    //e.g. PS-101F: [NO STANDING 7AM-10AM]<==[4PM-7PM EXCEPT SUNDAY <->] 3 HMP COMMERCIAL VEHICLES ONLY 10AM-4PM EXCEPT SUNDAY <->
    for ($i = count($regs) - 2; $i >= 0; $i--) {
        if ($regs[$i+1]->days and empty($regs[$i]->days)) {
            $regs[$i]->days = $regs[$i+1]->days;
        }
        if ($regs[$i+1]->arrow and empty($regs[$i]->arrow)) {
            $regs[$i]->arrow = $regs[$i+1]->arrow;
        }
    }

    
    if (count($regs) == 1) {
        //AVO implies all days
        //e.g. PS-100D: STAR (SYMBOL) AVO MTA POLICE <-> 
        if ($regs[0]->type == 'A' and empty($regs[0]->days)) {
            $regs[0]->days = 'A';
        }
        //for a single regulation all times is implied
        //e.g. PS-382G: NO PARKING SATURDAY SUNDAY HOLIDAYS <-> 
        if (empty($regs[0]->times) and !empty($regs[0]->days)) {
            $regs[0]->times = [0, 0];
        }
    }

    return $regs;
}


if (cmd('interpret_signs')) {
    $result = $conn->query('
with sign_types as (
    select 
        mutcd_code, 
        max(description) description 
    from sign 
    group by mutcd_code
) select 
    coalesce(t2.mutcd_code, t1.mutcd_code) mutcd_code,
    max(coalesce(t2.description, t1.description)) description
from sign_types t1
left join supersedes su on t1.mutcd_code = su.mutcd_code
left join sign_types t2 on su.by = t2.mutcd_code
where coalesce(t2.mutcd_code, t1.mutcd_code) not in (
    select mutcd_code from sign_regulation group by mutcd_code
)
group by coalesce(t2.mutcd_code, t1.mutcd_code)
order by mutcd_code') or trigger_error(print_r($conn->errorInfo(), true), E_USER_ERROR);
    
    $signs = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $total = 0;
    $done = 0;
    $valid = 0;
    $invalid = 0;
    $conn->beginTransaction();
    foreach ($signs as $sign) {
        echo $sign['mutcd_code'] . ': ' . $sign['description'] . "\n";
        $regs = interpret_sign($sign['mutcd_code'], $sign['description']);
        
        $all_valid = true;
        $fully_interpreted = true;
        
        foreach ($regs as $reg) {
            if (!$reg->valid()) {
                if (!empty($reg->days) and $reg->type != 'A') {
                    echo '@@@';
                }
                $all_valid = false;
            }
        
            if (!empty($reg->extra) and $reg->type != 'A') {
                echo '[', $reg->extra, ']';
                $fully_interpreted = false;
            }
            
            echo $reg, ', ';
            $reg->insert($conn);
        }
        
        $total++;
        
        if ($fully_interpreted) {
            $done++;
        }
        
        if ($all_valid) {
            $valid++;
        } else {
            $invalid++;
        }
        
        echo "\n";
    }
    
    //invalidate partly valid signs
    $conn->query('
update sign_regulation 
set checked = null 
where mutcd_code in (
    select 
        mutcd_code 
    from sign_regulation 
    where checked is null)');
    
    
    $conn->commit();
    
    echo "$done / $total signs fully interpreted, " . ($valid - $done) . " partly interpreted, $invalid invalid";
}

function array_set($value, &$a, ...$keys) {
    $current = &$a;
    foreach ($keys as $k) {
        if (!isset($current[$k])) {
            $current[$k] = [];
        }
        $current = &$current[$k];
    }
    $current = $value;
}

if (cmd('boroughs')) {
    $boro_parking = $conn->query('select
        cb.borocode,
        p.day,
        p.period,
        p.type,
        sum(p.length) length
from parking p
join order_segment os on os.order_no = p.order_no
left join blockface_geom bg on bg.blockface = os.blockface
join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
group by cb.borocode, p.day, p.period, p.type')->fetchAll(PDO::FETCH_ASSOC);
    $boro_geom = $conn->query('select
    borocode,
    boroname,
    ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geojson
    from borough')->fetchAll(PDO::FETCH_ASSOC);
    $all = [];
    foreach ($boro_geom as $row) {
        $all[$row['borocode']] = [
            'name' => $row['boroname'],
            'geom' => json_decode($row['geojson']),
            'parking' => []
        ];
    }
    
    foreach ($boro_parking as $row) {
        array_set((int)$row['length'], $all, $row['borocode'], 'parking', $row['day'], $row['period'], $row['type']);
    }
    
    @mkdir("data", 0777, true);
    file_put_contents("data/boroughs.json", json_encode($all));
}


if (cmd('tracts')) {
    $tract_parking = $conn->prepare('
        select
                cb.ct2010,
                p.day,
                p.period,
                p.type,
                sum(p.length) length
        from parking p
        join order_segment os on os.order_no = p.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
        where cb.borocode = :boro
        group by cb.ct2010, p.day, p.period, p.type');
    $tract_geom = $conn->prepare('
        select 
            ct2010, 
            ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geojson 
            from census_tract ct 
        where ct.borocode = :boro');
    $block_geom = $conn->prepare('
        select 
            cb2010, 
            ST_AsGeoJSON(ST_Transform(geom, 4326), 6, 0) geojson 
        from census_block
        where borocode = :boro and ct2010 = :ct2010');
    $block_parking = $conn->prepare('
        select
                cb.cb2010,
                p.day,
                p.period,
                p.type,
                sum(p.length) length
        from parking p
        join order_segment os on os.order_no = p.order_no
        left join blockface_geom bg on bg.blockface = os.blockface
        join census_block cb on cb.bctcb2010 = coalesce(bg.bctcb2010, os.bctcb2010)
        where cb.borocode = :boro
        and cb.ct2010 = :ct2010
        group by cb.cb2010, p.day, p.period, p.type');
    foreach ([1, 2, 3, 4, 5] as $boro) {
        $all = [];
        
        $tract_geom->execute(['boro' => $boro]);
        $tg = $tract_geom->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tg as $row) {
            $all[$row['ct2010']] = [
                'geom' => json_decode($row['geojson']),
                'parking' => [],
            ];
        
        }
        
        $tract_parking->execute(['boro' => $boro]);
        $tp = $tract_parking->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tp as $row) {
            array_set((int)$row['length'], $all, $row['ct2010'], 'parking', $row['day'], $row['period'], $row['type']);
        }
        
        @mkdir("data/$boro",0777, true);
        file_put_contents("data/$boro/tracts.json", json_encode($all));
        
        $tracts = array_keys($all);
        unset($all);
        

        foreach ($tracts as $tract) {
            $block_geom->execute(['boro' => $boro, 'ct2010' => $tract]);
            $result = $block_geom->fetchAll(PDO::FETCH_ASSOC);
            $all = [];
            foreach ($result as $row) {
                $all[$row['cb2010']] = [
                    'geom' => json_decode($row['geojson']),
                    'parking' => [],
                ];
            }
            
            $block_parking->execute(['boro' => $boro, 'ct2010' => $tract]);
            $result = $block_parking->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as $row) {
                array_set((int)$row['length'], $all, $row['cb2010'], 'parking', $row['day'], $row['period'], $row['type']);
            }
            @mkdir("data/$boro/$tract",0777, true);
            file_put_contents("data/$boro/$tract/blocks.json", json_encode($all));
        }
    }
}

if (cmd('blocks')) {
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $census_blocks = $conn->query('select bctcb2010 from census_block b group by bctcb2010;')->fetchAll(PDO::FETCH_COLUMN);
    $conn->beginTransaction();
    
    $get_signs = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            os.order_no,
            s.seq,
            s.distx,
            s.arrow,
            sr.mutcd_code,
            sr.type,
            sr.days,
            sr.start_time,
            sr.end_time,
            sr.arrow as arrow_type,
            sr.extra,
            sr.checked
        from sign s
        join sign_regulation sr on s.mutcd_code = sr.mutcd_code
        join order_segment os on os.order_no = s.order_no
        left join (select * from blockface_geom natural join b) bg on bg.blockface = os.blockface
        join b on coalesce(bg.bctcb2010, os.bctcb2010) = b.bctcb2010
        order by os.order_no, s.seq
    ');
    $get_geometry = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            os.order_no,
            l.main_st,
            l.from_st,
            l.to_st,
            l.sos,
            ST_AsGeoJSON(ST_Transform(ST_Union(geom), 4326), 6, 0) geojson
        from (select * from blockface_geom natural join b) bg
        full outer join order_segment os on bg.blockface = os.blockface
        left join location l on l.order_no = os.order_no
        join b on coalesce(bg.bctcb2010, os.bctcb2010) = b.bctcb2010
        group by os.order_no, l.main_st, l.from_st, l.to_st, l.sos
    ');
    $get_parking = $conn->prepare('
        with b (bctcb2010) as (select :bctcb2010)
        select
            os.order_no,
            p.day,
            p.period,
            p.type,
            sum(p.length) length
        from order_segment os
        left join (select * from blockface_geom natural join b) bg on bg.blockface = os.blockface
        join parking p on p.order_no = os.order_no
        where coalesce(bg.bctcb2010, os.bctcb2010) = :bctcb2010
        group by os.order_no, p.day, p.period, p.type');
    
    $block_count = 0;
    
    if (isset($argv[2])) {
        $census_blocks = array_slice($argv,2);
    }
    
    foreach ($census_blocks as $census_block) {
        $borough = substr($census_block, 0, 1);
        $tract = substr($census_block, 1, 6);
        $block = substr($census_block, 7);
        @mkdir("data/$borough/$tract",0777, true);
        
        $r = $get_signs->execute(['bctcb2010' => $census_block]);
        if (!$r) {
            trigger_error(print_r($get_signs->errorInfo(), true), E_USER_ERROR);
        }
        $signs = $get_signs->fetchAll(PDO::FETCH_ASSOC);
        
        $r = $get_geometry->execute(['bctcb2010' => $census_block]);
        if (!$r) {
            trigger_error(print_r($get_geometry->errorInfo(), true), E_USER_ERROR);
        }
        $geometry = $get_geometry->fetchAll(PDO::FETCH_ASSOC);
        
        $r = $get_parking->execute(['bctcb2010' => $census_block]);
        if (!$r) {
            trigger_error(print_r($get_parking->errorInfo(), true), E_USER_ERROR);
        }
        $parking = $get_parking->fetchAll(PDO::FETCH_ASSOC);
        
        $all = [];
        
        foreach ($geometry as $g) {
            $order = $g['order_no'];
            if (!isset( $all[$order] )) {
                $all[$order] = [
                    'signs' => [],
                    'geom' => json_decode($g['geojson']),
                    'main_st' => $g['main_st'],
                    'from_st' => $g['from_st'],
                    'to_st' => $g['to_st'],
                    'parking' => [],
                ];
            }
        }
        
        foreach ($signs as $sign) {
            $order = $sign['order_no'];
            unset($sign['order_no']);
            $all[$order]['signs'][] = $sign;
        }
        
        foreach ($parking as $p) {
            array_set((int)$p['length'], $all, $p['order_no'], 'parking', $p['day'], $p['period'], $p['type']);
        }
        
        file_put_contents("data/$borough/$tract/$block.json", json_encode($all));

        echo "$block_count/" . count($census_blocks) . " blocks\r";
        $block_count++;
    }
    echo "\n";
    $conn->commit();
}

if (cmd('parking')) {
    $orders = $conn->query('select order_no from sign group by order_no')->fetchAll(PDO::FETCH_COLUMN);
    
    if (isset($argv[2])) {
        $orders = array_slice($argv, 2);
    }
    
    $get_signs = $conn->prepare('
select 
    s.distx,
    coalesce(su.by, s.mutcd_code) mutcd_code,
    sr.days,
    sr.type,
    sr.start_time,
    sr.end_time,
    sr.arrow,
    sr.checked,
    sr.extra
from sign s
left join supersedes su on s.mutcd_code = su.mutcd_code
join sign_regulation sr on sr.mutcd_code = coalesce(su.by, s.mutcd_code)
where s.order_no = :order_no
order by s.distx, s.seq');
    $insert_parking = $conn->prepare('
            insert into parking (
                order_no, 
                start,
                length, 
                day, 
                period, 
                type
            ) values (
                :order_no, 
                :start,
                :length, 
                :day, 
                :period, 
                :type
            )');
            
            
    $n = 0;
    $l = 0;
    $conn->beginTransaction();
    
    foreach ($orders as $order) {
        if (!$get_signs->execute(['order_no' => $order])) {
            trigger_error($get_signs->errorInfo(), E_USER_ERROR);
        }
        $result = $get_signs->fetchAll(PDO::FETCH_ASSOC);
        
        $poles = [];
        
        foreach ($result as $row) {
            if (!isset($poles[$row['distx']])) {
                $poles[$row['distx']] = [];
            }
            $reg = new Regulation();
            $reg->mutcd_code = $row['mutcd_code'];
            $reg->days = trim($row['days']);
            $reg->type = trim($row['type']);
            $reg->times = [parse_time($row['start_time']), parse_time($row['end_time'])];
            $reg->arrow = $row['arrow'];
            $reg->extra = $row['extra'];
            $reg->checked = $row['checked'];
                
            $poles[$row['distx']][] = $reg;
        }
        
        $parking = [];
        
        $first_pole = true;
        $start = 0;
        $current_regs = [];
        $next_regs = [];
        
        for ($regs = reset($poles), $distance = key($poles); 
            key($poles) !== null; 
            $regs = next($poles), $distance = key($poles)) {
            
            $end = false;
            
            foreach ($regs as $reg) {
                if (!$reg->valid()) {
                    if (count($regs) == 1 and (
                        $reg->mutcd_code == 'CL' or 
                        $reg->mutcd_code == 'BL' or 
                        $reg->mutcd_code == 'PL')) {
                        //this curb/building ends a block, write out the current regs
                        if (!empty($current_regs)) {
                            $parking[] = ['start' => $start, 'length' => $distance - $start, 'regs' => $current_regs];
                            $current_regs = $next_regs; //next_regs should always be [] at this point
                            $next_regs = [];
                            
                            //$first_pole = true; ???
                        }
                        //if this is a curb or building line don't count distance before this
                        $start = $distance;
                    }
                } else {
                    if ($reg->arrow == '-->') {
                        if ($first_pole) {
                            //a single arrow regulation on the first pole points back towards the curb
                            // if it doesn't match a sign on the next pole
                            
                            //peek at the next pole
                            $next = next($poles);
                            prev($poles);
                            //if none match this, this extends backwards to the curb
                            if (!any(array_map(
                                function($reg2) use ($reg) {
                                    return $reg->equals($reg2);
                                }, $next))) {
                                $current_regs[] = $reg;
                                $end = true;
                            //otherwise it points forward (but dedup)
                            } elseif (!any(array_map(
                                function($reg2) use ($reg) {
                                    return $reg->equals($reg2);
                                }, $current_regs))) {
                                $next_regs[] = $reg;
                            }
                        } else {
                            //a single arrow regulation matching the current regulation points backwards
                            if (any(array_map(
                                function($reg2) use ($reg) {
                                    return $reg->equals($reg2);
                                }, $current_regs))) {
                                $end = true;
                            //if arrow on previous pole pointed backward, this reg does too 
                            //TODO: how to handle a multiple-sign reg? e.g. 2 pointing backward, 1 pointing forward
                            } elseif (empty($current_regs)) {
                                $current_regs[] = $reg;
                                $end = true;
                            //a single arrow regulation not matching the current regulation points forwards
                            } else {
                                $next_regs[] = $reg;
                            }
                        }
                    } else {
                        //a double arrow regulation extends back if there is no current regulation
                        if ($first_pole or empty($current_regs)) {
                            if (!any(array_map(
                                function($reg2) use ($reg) {
                                    return $reg->equals($reg2);
                                }, $current_regs))) {
                                $current_regs[] = $reg;
                            }
                        //otherwise pretend it's a forward arrow
                        } else {
                            if (!any(array_map(
                                function($reg2) use ($reg) {
                                    return $reg->equals($reg2);
                                }, $current_regs))) {
                                $next_regs[] = $reg;
                            }
                        }
                    }
                }
            }
            
            if (!empty($current_regs)) {
                $first_pole = false;
            }
            
            if ($end or !empty($next_regs)) {
                $parking[] = ['start' => $start, 'length' => $distance - $start, 'regs' => $current_regs];
                $start = $distance;
                $current_regs = $next_regs;
                $next_regs = [];
                
                $first_pole = false;
                $end = false;
            }
        }
        
        if (isset($argv[2])) {
            print_r($parking);
        }
        
        foreach ($parking as $stretch) {
            if ($stretch['length'] == 0) continue;
            for ($day = 0; $day < 7; $day++) {
                $periods = [
                    'night' => [0,6*60*60], 
                    'morning rush' => [7*60*60,10*60*60], 
                    'daytime' => [10*60*60,16*60*60], 
                    'evening rush' => [16*60*60,19*60*60], 
                    'evening' => [19*60*60,0]
                ];
                
                $priority = array_flip(['A', 'O', 'S', 'P', 'MC', 'M', 'C', 'L', '']);
                
                foreach ($periods as $label => $times) {
                    $type = null;
                    foreach ($stretch['regs'] as $reg) {
                        for ($t = $times[0];
                            ($times[1] > $times[0])
                                ? ($t < $times[1]) 
                                : (($t >= $times[0]) or ($t < $times[1]));
                            $t = ($t + 30*60) % (24*60*60)) {
                            if ($reg->applies($day, $t) and $priority[$reg->type] < $priority[$type]) {
                                $type = $reg->type;
                            }
                        }
                    }
                    $insert_parking->execute([
                        'order_no' => $order, 
                        'start' => $stretch['start'], 
                        'length' => $stretch['length'], 
                        'day' => $day, 
                        'period' => $label, 
                        'type' => $type
                        ]) or trigger_error(print_r($insert_parking->errorInfo(), true), E_USER_ERROR);;
                }
            }
            $l += $stretch['length'];
        }
        $n++;
        echo "$n/" . count($orders) . " processed, $l ft of curb\r";
    }
    $conn->commit();
    echo "\n";
}
