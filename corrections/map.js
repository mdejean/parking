"use strict";

proj4.defs('FIPS:3104','+proj=lcc +lat_1=40.66666666666666 +lat_2=41.03333333333333 +lat_0=40.16666666666666 +lon_0=-74 +x_0=300000 +y_0=0 +ellps=GRS80 +datum=NAD83 +to_meter=0.3048006096012192 +no_defs ');

var map = L.map('map');

// add an OpenStreetMap tile layer
L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
    className: 'tiles',
    maxZoom: 19
}).addTo(map);

var orders = null;

var selected = null;
var order = null;

var boro = Math.floor(Math.random() * 5) + 1;

if (window.location.search) {
    let sp = new URLSearchParams(window.location.search);
    if (sp.get('boro')) {
        boro = sp.get('boro');
    }
}

async function load() {
    let f = await fetch('data/blockface/' + boro + '.json');
    let blockfaces = await f.json();
    for (let blockface in blockfaces) {
        let feature = L.GeoJSON.asFeature(blockfaces[blockface]);
        feature.properties.blockface = blockface;
        features_layer.addData(feature);
    }
    
    f = await fetch('data/order/' + boro + '.json');
    orders = await f.json();
    
    next();
}

function next() {
    order = orders[Math.floor(Math.random() * orders.length)];
    
    document.getElementById('sos').innerHTML = order.sos;
    document.getElementById('main_st').innerHTML = order.main_st;
    document.getElementById('from_st').innerHTML = order.from_st;
    document.getElementById('to_st').innerHTML = order.to_st;
    
    from.setContent(order.from_st);
    to.setContent(order.to_st);
    
    if (order.blockface) {
        features_layer.eachLayer((l) => {
            if (l.feature.properties.blockface == order.blockface) {
                select(l);
                map.fitBounds(l.getBounds(), {padding: [50, 50]});
            }
        });
    } else {
        selected = null;
        map.setView([40.7358,-73.9243], 10);
        from.remove();
        to.remove();
    }
    features_layer.resetStyle();
}

var from = L.popup();
var to = L.popup();
var reversed = false;

function select(l) {
    selected = l.feature.properties.blockface; 
    features_layer.resetStyle();
    
    let lls = l.getLatLngs();
    let last_lstr = lls[lls.length - 1];
    let last_ll = last_lstr[last_lstr.length - 1];
    from.setLatLng(reversed ? lls[0][0] : last_ll);
    to.setLatLng(reversed ? last_ll : lls[0][0]);
    
    from.addTo(map);
    to.addTo(map);
}

var features_layer = L.geoJSON(
    [],
    {
        style: (feature) => {
            if (selected == feature.properties.blockface) 
                return  {color: "red"};
            return  {color: "blue"};
        },
        onEachFeature: (feature, layer) => {
            layer.on({
                'click': (e) => {
                    select(e.target);
                }
            });
        }
    }
).addTo(map);

map.setView([40.7358,-73.9243], 10);

window.addEventListener('load', load);
document.getElementById('reverse').addEventListener('click', 
    () => {
        reversed = !reversed;
        let t = from.getLatLng();
        from.setLatLng(to.getLatLng());
        to.setLatLng(t);
        
        from.addTo(map);
        to.addTo(map);
    });

let rows = JSON.parse(window.localStorage.getItem('corrections')) || [];

document.getElementById('submit').addEventListener('click',
    () => {
        rows.push([order.order_no, selected, reversed]);
        window.localStorage.setItem('corrections', JSON.stringify(rows));
        
        next();
    });

document.getElementById('skip').addEventListener('click',
    () => {
        rows.push([order.order_no, null, null]);
        window.localStorage.setItem('corrections', JSON.stringify(rows));
        next();
    });

document.getElementById('save').addEventListener('click', 
    () => {
        let s = JSON.stringify(rows);
        s = s.replaceAll("],", "\n");
        s = s.replaceAll("]", "");
        s = s.replaceAll("[", "");
        s = "order_no, blockface, reversed\n" + s;
        window.open('mailto:cs@dejean.nyc?subject=parking-map-geocoding&body=' + encodeURIComponent(s));
    });
