var _table_ = document.createElement('table'),
    _tr_ = document.createElement('tr'),
    _th_ = document.createElement('th'),
    _td_ = document.createElement('td');

function buildHtmlTable(arr, formatters) {
    if (typeof formatters == 'undefined') formatters = {}; 
    var table = _table_.cloneNode(false),
        columns = addAllColumnHeaders(arr, table);
    for (let row of arr) {
        var tr = _tr_.cloneNode(false);
        for (let column of columns) {
            var td = _td_.cloneNode(false);
            let formatter = formatters[column] || ((s) => document.createTextNode(s));
            let cell = formatter(row[column] || '');
            td.appendChild(cell);
            tr.appendChild(td);
        }
        table.appendChild(tr);
    }
    return table;
}

// Adds a header row to the table and returns the set of columns.
// Need to do union of keys from all records as some records may not contain
// all records
function addAllColumnHeaders(arr, table)
{
    var columnSet = [],
        tr = _tr_.cloneNode(false);
    for (var i=0, l=arr.length; i < l; i++) {
        for (var key in arr[i]) {
            if (arr[i].hasOwnProperty(key) && columnSet.indexOf(key)===-1) {
                columnSet.push(key);
                var th = _th_.cloneNode(false);
                th.appendChild(document.createTextNode(key));
                tr.appendChild(th);
            }
        }
    }
    table.appendChild(tr);
    return columnSet;
}
