"use strict";

proj4.defs('FIPS:3104','+proj=lcc +lat_1=40.66666666666666 +lat_2=41.03333333333333 +lat_0=40.16666666666666 +lon_0=-74 +x_0=300000 +y_0=0 +ellps=GRS80 +datum=NAD83 +to_meter=0.3048006096012192 +no_defs ');

var map = L.map('map');

// add an OpenStreetMap tile layer
L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
    className: 'tiles'
}).addTo(map);



var data = {
    labels: [],
    datasets: [],
};

let boroughs = [null, "Manhattan", "The Bronx", "Brooklyn", "Queens", "Staten Island"];

let days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

let periods = {
    'night': "night",
    'morning rush': "morning rush",
    'daytime': "daytime",
    'evening rush': "evening rush",
    'evening': "evening"
};

let types = {
    'O':  {label: "No Stopping",                color: "#f88"},
    'S':  {label: "No Standing",                color: "#c88"},
    'P':  {label: "No Parking",                 color: "#a88"},
    'A':  {label: "Authorized Vehicles",        color: "#f8f"},
    'MC': {label: "Metered Commerical Parking", color: "#88f"},
    'C':  {label: "Commerical Vehicles",        color: "#88a"},
    'M':  {label: "Metered Parking",            color: "#888"},
    'L':  {label: "Time Limited Parking",       color: "#8a8"},
    '':   {label: "Free Parking",               color: "#8f8"}
};

function reset_data() {
    data = {
        labels: [],
        datasets: [],
    };
    
    let empty = [];
    for (let day of days) {
        for (let period in periods) {
            data.labels.push(day + " " + periods[period]);
            empty.push(-0.0);
        }
    }

    for (let type in types) {
        data.datasets.push({
            backgroundColor: types[type].color,
            categoryPercentage: 1.0,
            barPercentage: 1.0,
            label: types[type].label,
            data: Array.from(empty)
        });
    }
}

async function calculate_parking() {
    let show_spaces = document.getElementById('tools').elements['show_spaces'].checked;
    
    reset_data();
    for (let day in days) {
        for (let period in periods) {
            for (let f of selection) {
                let p = show_spaces ? f.properties.parking_spaces : f.properties.parking_length;
                for (let type in types) {
                    for (const ds of data.datasets) {
                        if (ds.label == types[type].label) {
                            ds.data[data.labels.indexOf(days[day] + " " + periods[period])]
                                += (p[day][period][type] || 0);
                        }
                    }
                }
            }
        }
    }
    chart.data = data;
    chart.options.scales.xAxes[0].scaleLabel = {
        display: true,
        labelString: (show_spaces ? "parking spaces" : "feet of curb"),
        padding: 0
    };
    chart.update();
}

reset_data();
var chart_ctx = document.getElementById('chart_canvas');
var chart = new Chart(chart_ctx, {
    type: 'horizontalBar',
    data: data,
    options: {
        scales: {
            xAxes: [{
                offset: false,
                stacked: true
            }],
            yAxes: [
                {
                    display: false,
                    stacked: true
                },
                {
                    type: 'category',
                    labels: days
                }
            ]
        },
        tooltips: {
            enabled: false,
            intersect: false
        },
        legend: {
            display: false
        },
        animation: {
            duration: 0 // general animation time
        },
        hover: {
            animationDuration: 0 // duration of animations when hovering an item
        },
        responsiveAnimationDuration: 0 // animation duration after a resize
    }
});

let ungeocoded = [];

let selection = new Set();

function select_invalid(e) {
    let order_no = e.target.value;
    
    for (let f of ungeocoded) {
        if (f.properties.order_no == order_no) {
            if (selection.has(f)) {
                e.target.innerHTML = 'Select';
                selection.delete(f);
            } else {
                selection.add(f);
                e.target.innerHTML = 'Deselect';
            }
            break;
        }
    }
    
    calculate_parking();
}

async function show_invalid() {
    document.getElementById('errors').innerHTML = 'Orders not geocoded <label for=show_invalid></label><br><input id=show_invalid type=checkbox>';
    document.getElementById('errors').appendChild(
        buildHtmlTable(
            ungeocoded.map((f) => f.properties),
            {
                borough: (b) => document.createTextNode(boroughs[b]),
                order_no: (order_no) => {
                    let b = document.createElement('button');
                    b.innerHTML = 'Select';
                    b.value = order_no;
                    b.addEventListener('click', select_invalid);
                    let div = document.createElement('div');
                    div.appendChild(b);
                    div.appendChild(document.createTextNode(order_no));
                    return div;
                },
                parking_length: (p) => {
                    let s = parking_stat(p);
                    return document.createTextNode("" + s.min + " (" + days[s.min_day] + " " + s.min_period + ") - " + s.max + " (" + days[s.max_day] + " " + s.max_period + ") ft");
                },
                parking_spaces: (p) => {
                    let s = parking_stat(p);
                    return document.createTextNode("" + s.min + " (" + days[s.min_day] + " " + s.min_period + ") - " + s.max + " (" + days[s.max_day] + " " + s.max_period + ") spaces");
                },
                signs: (s) => document.createTextNode(s.length + " signs")
            })
        );
}

async function select(feature, layer) {
    if (selection.has(feature)) {
        selection.delete(feature);
    } else {
        selection.add(feature);
    }

    calculate_parking();
    features_layer.resetStyle();
}

async function split(feature, layer) {
    let f = null;
    
    switch (feature.properties.type) {
        case 'borough':
            f = await fetch('data/'
                + feature.properties.borough + '/tracts.json');
            break;
        case 'tract':
            f = await fetch('data/' 
                + feature.properties.borough + '/' 
                + feature.properties.tract + '/blocks.json');
            break;
        case 'block':
            f = await fetch('data/' 
                + feature.properties.borough + '/' 
                + feature.properties.tract + '/' 
                + feature.properties.block + '.json');
            break;
        default:
            return; //maybe show signs?
    }
    
    let response = await f.json();
    
    const split_type = {borough: 'tract', tract: 'block', block: 'order'};
    
    for (let rowid in response) {
            let new_feature = L.GeoJSON.asFeature(response[rowid]['geom'] || {});
            new_feature.properties.type = split_type[feature.properties.type];
            switch (new_feature.properties.type) {
                case 'order':
                    new_feature.properties.order_no = rowid;
                    new_feature.properties.main_st = response[rowid]['main_st'];
                    new_feature.properties.from_st = response[rowid]['from_st'];
                    new_feature.properties.to_st = response[rowid]['to_st'];
                    new_feature.properties.signs = response[rowid]['signs'];
                case 'block':
                    new_feature.properties.block = feature.properties.block || rowid;
                case 'tract':
                    new_feature.properties.tract = feature.properties.tract || rowid;
                default:
                    new_feature.properties.borough = feature.properties.borough;
                    new_feature.properties.parking_length = response[rowid]['parking_length'];
                    new_feature.properties.parking_spaces = response[rowid]['parking_spaces'];
            }
            if (response[rowid]['geom']) {
                features_layer.addData(new_feature);
            } else {
                //add features missing geometry to the beginning of the table
                ungeocoded.unshift(new_feature);
            }
            
            if (selection.has(feature)) {
                selection.add(new_feature);
            }
    }
    
    if (selection.has(feature)) {
        selection.delete(feature);
    }
    
    layer.remove();
    features_layer.resetStyle();
    show_invalid();
}

async function load() {
    let f = await fetch('data/boroughs.json');
    let boroughs = await f.json();
    for (let borough in boroughs) {
        let feature = L.GeoJSON.asFeature(boroughs[borough]['geom']);
        feature.properties.type = 'borough';
        feature.properties.borough = borough;
        feature.properties.name = boroughs[borough]['name'];
        feature.properties.parking_length = boroughs[borough]['parking_length'];
        feature.properties.parking_spaces = boroughs[borough]['parking_spaces'];
        features_layer.addData(feature);
    }
    
    f = await fetch('data/ungeocoded.json');
    let response = await f.json();
    for (let rowid in response) {
            let new_feature = L.GeoJSON.asFeature({});
            new_feature.properties.order_no = rowid;
            for (let k of Object.keys(response[rowid])) {
                new_feature.properties[k] = response[rowid][k];
            }
            ungeocoded.push(new_feature);
    }
    show_invalid();
}

function doTool(e) {
    let layer = e.target;
    let feature = layer.feature;
    if (document.getElementById('tools').elements['tool'].value == 'select') {
        select(feature, layer);
    } else {
        split(feature, layer);
    }
}

function parking_stat(p) {
    let r = {
        min: Infinity,
        min_day: null,
        min_period: null,
        max: 0,
        max_day: null,
        max_period: null
    };
    
    if (!p || Object.keys(p).length == 0) return r;
    
    for (let day in days) {
        for (let period in periods) {
            let val = p[day][period][''] || 0;
            if (val < r.min) {
                r.min = val;
                r.min_day = day;
                r.min_period = period;
            }
            
            if (val > r.max) {
                r.max = val;
                r.max_day = day;
                r.max_period = period;
            }
        }
    }
    
    return r;
}

function updateTooltip(e) {
    let layer = e.target;
    let feature = layer.feature;
    let show_spaces = document.getElementById('tools').elements['show_spaces'].checked;
    
    let stat = parking_stat(show_spaces ? feature.properties.parking_spaces : feature.properties.parking_length);
    
    let tooltip_text = "";
    if (feature.properties.type == 'order') {
        if (feature.properties.order_no) {
            tooltip_text += "<h4>#" + feature.properties.order_no 
                + "</h4><div>" + feature.properties.sos 
                + " side of " + feature.properties.main_st 
                + "<br>(" + feature.properties.from_st 
                + " - " + feature.properties.to_st + ")</div>"
                + "<div>" + boroughs[feature.properties.borough] 
                + " tract " + feature.properties.tract 
                + " block " + feature.properties.block 
                + "</div>";
        } else {
            tooltip_text += "<h4>Not geocoded</h4>"
                + "<div>Approximately " + feature.properties.length + " ft</div>";
        }
    } else if (feature.properties.type == 'block') {
        tooltip_text +=
            "<h4>" + boroughs[feature.properties.borough] 
            + " tract " + feature.properties.tract 
            + " block " + feature.properties.block 
            + "</h4>";
    } else if (feature.properties.type == 'tract') {
        tooltip_text +=
            "<h4>" + boroughs[feature.properties.borough] 
            + " tract " + feature.properties.tract 
            + "</h4>";
    } else if (feature.properties.type == 'borough') {
        tooltip_text +=
            "<h4>" + boroughs[feature.properties.borough] 
            + "</h4>";
    }
    
    tooltip_text += "<div>" + stat.min + " (" + days[stat.min_day] + " " + periods[stat.min_period] 
    + ") to " + stat.max + " (" + days[stat.max_day] + " " + periods[stat.max_period] + ") " + (show_spaces ? "spaces" : "feet") + "</div>"; 
    
    layer.bindTooltip(tooltip_text);
    layer.openTooltip();
}

function closeTooltip(e) {
    let layer = e.target;
    let feature = layer.feature;
    layer.unbindTooltip();
}

var features_layer = L.geoJSON(
    [],
    {
        style: (feature) => {
            if (selection.has(feature)) {
                return {color: "red"};
            } else {
                return {color: "green"};
            }
        },
        onEachFeature: (feature, layer) => {
            layer.on({
                'click': doTool,
                'mouseover': updateTooltip,
                'mouseout': closeTooltip
            });
        }
    }
).addTo(map);

map.setView([40.7358,-73.9243], 10);

var toolbox = L.control({position: 'topright'});

function tool(id) {
    let parent = new DocumentFragment();
    let r = document.createElement('input');
    r.type = 'radio';
    r.name = 'tool';
    r.value = id;
    r.id = id;
    let l = document.createElement('label');
    l.className = 'tool';
    l.htmlFor = id;
    parent.appendChild(r);
    parent.appendChild(l);
    return parent;
}

function checkbox(id, checked) {
    let parent = new DocumentFragment();
    let r = document.createElement('input');
    r.type = 'checkbox';
    r.name = id;
    r.id = id;
    r.checked = checked || false;
    let l = document.createElement('label');
    l.htmlFor = id;
    parent.appendChild(r);
    parent.appendChild(l);
    return parent;
}

toolbox.onAdd = (map) => {
    let form = L.DomUtil.create('form', 'legend');
    form.id = 'tools';
    form.appendChild(tool('select'));
    form.appendChild(tool('split'));
    form.appendChild(checkbox('show_spaces', true));
    form.elements['show_spaces'].addEventListener('click', calculate_parking);
    return form;
};

toolbox.addTo(map);

window.addEventListener('load', load);
