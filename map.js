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
    if ('order_no' in feature.properties) {
        return; //maybe show signs?
    } else if ('block' in feature.properties) {
        f = await fetch('data/' 
            + feature.properties.borough + '/' 
            + feature.properties.tract + '/' 
            + feature.properties.block + '.json');
    } else if ('tract' in feature.properties) {
        f = await fetch('data/' 
            + feature.properties.borough + '/' 
            + feature.properties.tract + '/blocks.json');
    } else if ('borough' in feature.properties) {
        f = await fetch('data/'
            + feature.properties.borough + '/tracts.json');
    }
    
    let response = await f.json();

    for (let rowid in response) {
        if (response[rowid]['geom']) {
            let new_feature = L.GeoJSON.asFeature(response[rowid]['geom']);
            new_feature.properties.borough = feature.properties.borough;
            new_feature.properties.tract = feature.properties.tract || rowid;
            if (feature.properties.tract) {
                new_feature.properties.block = feature.properties.block || rowid;
            }
            if (feature.properties.block) {
                new_feature.properties.order_no = rowid;
                new_feature.properties.main_st = response[rowid]['main_st'];
                new_feature.properties.from_st = response[rowid]['from_st'];
                new_feature.properties.to_st = response[rowid]['to_st'];
                new_feature.properties.signs = response[rowid]['signs'];
            }
            
            new_feature.properties.parking = response[rowid]['parking'];
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
}

let selection = new Set();

async function select(feature, layer) {
    if (selection.has(feature)) {
        selection.delete(feature);
        layer.setStyle({color: "green"});
    } else {
        selection.add(feature);
        layer.setStyle({color: "red"});
    }

    calculate_parking();
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
    'O':  "No Stopping",
    'S':  "No Standing",
    'P':  "No Parking",
    'A':  "Authorized Vehicles",
    'MC': "Metered Commerical Parking",
    'C':  "Commerical Vehicles",
    'M':  "Metered Parking",
    'L':  "Time Limited Parking",
    '':   "Free Parking"
};

let colors = {
    'P': "#a88",
    'S': "#c88",
    'O': "#f88",
    'A': "#f8f",
    'MC': "#88f",
    'C': "#88a",
    'M': "#888",
    'L': "#8a8",
    ''  : "#8f8"
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
            backgroundColor: colors[type],
            categoryPercentage: 1.0,
            barPercentage: 1.0,
            label: types[type],
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
                                += (Number.parseInt(f.properties.parking[day][period][type]) || 0);
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
        feature.properties.borough = borough;
        feature.properties.parking = boroughs[borough]['parking'];
        features_layer.addData(feature);
    }
}


var features_layer = L.geoJSON(
    [],
    {
        style: {color: "green"},
        onEachFeature: (feature, layer) => {
            layer.on('click', () => {
                if (tools.getContainer().elements['tool'].value == 'select') {
                    select(feature, layer);
                } else {
                    split(feature, layer);
                }
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
