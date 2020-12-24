"use strict";

proj4.defs('FIPS:3104','+proj=lcc +lat_1=40.66666666666666 +lat_2=41.03333333333333 +lat_0=40.16666666666666 +lon_0=-74 +x_0=300000 +y_0=0 +ellps=GRS80 +datum=NAD83 +to_meter=0.3048006096012192 +no_defs ');

var map = L.map('map');

// add an OpenStreetMap tile layer
L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
    className: 'tiles'
}).addTo(map);

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
        if (response[rowid]['geom']) {
            let new_feature = L.GeoJSON.asFeature(response[rowid]['geom']);
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
                    new_feature.properties.parking = response[rowid]['parking'];
            }
            features_layer.addData(new_feature);
            
            if (selection.has(feature)) {
                selection.add(new_feature);
            }
        } else {
            //features missing geometry
        }
    }
    
    if (selection.has(feature)) {
        selection.delete(feature);
    }
    
    layer.remove();
    features_layer.resetStyle();
}

let selection = new Set();

async function select(feature, layer) {
    if (selection.has(feature)) {
        selection.delete(feature);
    } else {
        selection.add(feature);
    }

    calculate_parking();
    features_layer.resetStyle();
}

var data = {
    labels: [],
    datasets: [],
};

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
reset_data();

var chart_ctx = document.getElementById('chart_canvas');
var chart = new Chart(chart_ctx, {
    type: 'horizontalBar',
    data: data,
    options: {
        scales: {
            xAxes: [{
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

async function calculate_parking() {
    reset_data();
    for (let day in days) {
        for (let period in periods) {
            for (let f of selection) {
                for (let type in types) {
                    for (const ds of data.datasets) {
                        if (ds.label == types[type]) {
                            ds.data[data.labels.indexOf(days[day] + " " + periods[period])]
                                += (f.properties.parking[day][period][type] || 0);
                        }
                    }
                }
            }
        }
    }
    chart.data = data;
    chart.update();
}

async function load() {
    let f = await fetch('data/boroughs.json');
    let boroughs = await f.json();
    for (let borough in boroughs) {
        let feature = L.GeoJSON.asFeature(boroughs[borough]['geom']);
        feature.properties.type = 'borough';
        feature.properties.borough = borough;
        feature.properties.name = boroughs[borough]['name'];
        feature.properties.parking = boroughs[borough]['parking'];
        features_layer.addData(feature);
    }
}

function doTool(e) {
    let layer = e.target;
    let feature = layer.feature;
    if (tools.getContainer().elements['tool'].value == 'select') {
        select(feature, layer);
    } else {
        split(feature, layer);
    }
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
            });
        }
    }
).addTo(map);

map.setView([40.7358,-73.9243], 10);

var tools = L.control({position: 'topright'});

function tool(id) {
    let r = document.createElement('input');
    r.type = 'radio';
    r.name = 'tool';
    r.value = id;
    let l = document.createElement('label');
    l.id = id;
    l.appendChild(r);
    return l;
}

tools.onAdd = (map) => {
    let form = L.DomUtil.create('form', 'tools');
    form.appendChild(tool('select'));
    form.appendChild(tool('split'));
    return form;
};
tools.addTo(map);

window.addEventListener('load', load);
