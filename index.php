<?php
function read_db_file() {
    $devices = [];
    $headers = [];
    
    $file = fopen('db.txt', 'r');
    $found_hdr = false;
    if ($file) {
        while (($line = fgets($file)) !== false) {
            $line = trim($line);
            if (!$found_hdr) {
                if (strpos($line, '#HDR:') === 0) {
                    $headers = explode(':', trim(substr($line, 5)));
                    $found_hdr = true;
                }
                continue;
            }
            // Nach #HDR: nur echte Kommentarzeilen überspringen (ganze Zeile beginnt mit #, nicht nur das Namensfeld)
            if ($line === '' || (strlen($line) > 0 && $line[0] === '#' && strpos($line, '#HDR:') !== 0)) {
                continue;
            }
            // Parse device data
            $device_data = explode(':', $line);
            if (count($device_data) === count($headers)) {
                $device = array_combine($headers, $device_data);
                $devices[] = $device;
            }
        }
        fclose($file);
    }
    return [$headers, $devices];
}

function write_db_file($headers, $devices, $filename = 'db.txt') {
    $lines = [];
    $lines[] = "# HDR info\n# Feld 0  name    = 6 chars old name, 9 chars new name\n# Feld 1  ip      = ip address\n# Feld 2  usrname\n# Feld 3  usrpwd\n# Feld 4  enapwd\n# Feld 5  ostype  = IOS/COS/NOS/JUNOS/EW = ( IOS / Cat OS / Native OS / JUNOS / ExtremeWare )\n# Feld 6  access  = TEL/SSH = ( Telnet / ssh ) please set the ssh key first!!\n# Feld 7  clear   = CL/NOCL/OFF = ( Clear / NoClear / Off - No Polling - Maintenance )\n# Feld 8  poll    = Poll interval = every X Minutes ( max 59 Minutes - will be extended )\n# Feld 9  llid    = Location ID\n# Feld 10 info    = Information\n# Feld 11 tid     = Ticket-id\n#\n#   please NEVER change the HDR line ......\n#\n#HDR:" . implode(':', $headers) . "\n";
    foreach ($devices as $device) {
        $row = [];
        foreach ($headers as $h) {
            $row[] = $device[$h];
        }
        $lines[] = implode(':', $row);
    }
    file_put_contents($filename, implode("\n", $lines) . "\n");
}

function export_log($headers, $devices) {
    $logdir = __DIR__ . '/log';
    if (!is_dir($logdir)) {
        mkdir($logdir, 0777, true);
    }
    $ts = date('Y_m_d_H_i');
    $filename = $logdir . "/cdb_{$ts}.txt";
    write_db_file($headers, $devices, $filename);
}

// Import-Funktion
$import_error = '';
if (isset($_POST['import_db']) && isset($_FILES['importfile'])) {
    $file = $_FILES['importfile']['tmp_name'];
    $content = file_get_contents($file);
    // Prüfen, ob Datei das HDR enthält
    if (strpos($content, '#HDR:') !== false) {
        move_uploaded_file($_FILES['importfile']['tmp_name'], 'db.txt');
        header('Location: index.php');
        exit;
    } else {
        $import_error = 'Ungültiges Format: #HDR: Zeile fehlt!';
    }
}

list($headers, $devices) = read_db_file();

// Calculate all hashes first to find duplicates
$hashes = array();
foreach ($devices as $device) {
    $hash = substr(md5($device['name'] . $device['ip']), 0, 10);
    if (!isset($hashes[$hash])) {
        $hashes[$hash] = 1;
    } else {
        $hashes[$hash]++;
    }
}

// Export-Funktion
if (isset($_GET['export'])) {
    $ts = date('Y_m_d_H_i');
    $filename = "cdb_{$ts}.txt";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $lines = [];
    $lines[] = "# HDR info\n# Feld 0  name    = 6 chars old name, 9 chars new name\n# Feld 1  ip      = ip address\n# Feld 2  usrname\n# Feld 3  usrpwd\n# Feld 4  enapwd\n# Feld 5  ostype  = IOS/COS/NOS/JUNOS/EW = ( IOS / Cat OS / Native OS / JUNOS / ExtremeWare )\n# Feld 6  access  = TEL/SSH = ( Telnet / ssh ) please set the ssh key first!!\n# Feld 7  clear   = CL/NOCL/OFF = ( Clear / NoClear / Off - No Polling - Maintenance )\n# Feld 8  poll    = Poll interval = every X Minutes ( max 59 Minutes - will be extended )\n# Feld 9  llid    = Location ID\n# Feld 10 info    = Information\n# Feld 11 tid     = Ticket-id\n#\n#   please NEVER change the HDR line ......\n#\n#HDR:" . implode(':', $headers) . "\n";
    foreach ($devices as $device) {
        $row = [];
        foreach ($headers as $h) {
            $row[] = $device[$h];
        }
        $lines[] = implode(':', $row);
    }
    echo implode("\n", $lines) . "\n";
    exit;
}

// Save-Funktion
if (isset($_GET['save'])) {
    $activedir = __DIR__ . '/aktive';
    if (!is_dir($activedir)) {
        mkdir($activedir, 0777, true);
    }
    $ts = date('Y_m_d_H_i');
    $filename = $activedir . "/cdb_{$ts}.txt";
    write_db_file($headers, $devices, $filename);
    header('Location: index.php');
    exit;
}

// Get edit index from GET parameter
$edit_row = isset($_GET['edit']) ? (int)$_GET['edit'] : -1;

// Editieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_db'])) {
    if (isset($_POST['edit_index'])) {
        $edit_index = (int)$_POST['edit_index'];
        foreach ($headers as $h) {
            $devices[$edit_index][$h] = $_POST[$h] ?? '';
        }
        write_db_file($headers, $devices);
        export_log($headers, $devices);
        
        // Preserve filter parameters and position
        $params = $_GET;
        unset($params['edit']);
        $params['position'] = $edit_index;
        $query_string = http_build_query($params);
        header('Location: index.php' . ($query_string ? '?' . $query_string : ''));
        exit;
    }
    // Kopieren
    if (isset($_GET['copy_index'])) {
        $copy_index = (int)$_GET['copy_index'];
        $copy = $devices[$copy_index];
        if (strpos($copy['name'], 'copy_') !== 0) {
            $copy['name'] = 'copy_' . $copy['name'];
        }
        
        // Create new array with copied row
        $new_devices = array();
        foreach ($devices as $i => $device) {
            $new_devices[] = $device;
            if ($i === $copy_index) {
                $new_devices[] = $copy;
            }
        }
        $devices = $new_devices;
        
        // Write to file
        $lines = [];
        $lines[] = "# HDR info\n# Feld 0  name    = 6 chars old name, 9 chars new name\n# Feld 1  ip      = ip address\n# Feld 2  usrname\n# Feld 3  usrpwd\n# Feld 4  enapwd\n# Feld 5  ostype  = IOS/COS/NOS/JUNOS/EW = ( IOS / Cat OS / Native OS / JUNOS / ExtremeWare )\n# Feld 6  access  = TEL/SSH = ( Telnet / ssh ) please set the ssh key first!!\n# Feld 7  clear   = CL/NOCL/OFF = ( Clear / NoClear / Off - No Polling - Maintenance )\n# Feld 8  poll    = Poll interval = every X Minutes ( max 59 Minutes - will be extended )\n# Feld 9  llid    = Location ID\n# Feld 10 info    = Information\n# Feld 11 tid     = Ticket-id\n#\n#   please NEVER change the HDR line ......\n#\n#HDR:" . implode(':', $headers) . "\n";
        foreach ($devices as $device) {
            $row = [];
            foreach ($headers as $h) {
                $row[] = $device[$h];
            }
            $lines[] = implode(':', $row);
        }
        file_put_contents('db.txt', implode("\n", $lines) . "\n");
        
        // Remove copy_index from URL parameters but keep filters and position
        $params = $_GET;
        unset($params['copy_index']);
        $params['position'] = $copy_index;
        $query_string = http_build_query($params);
        header('Location: index.php' . ($query_string ? '?' . $query_string : ''));
        exit;
    }
    // Löschen
    if (isset($_POST['delete_index'])) {
        $delete_index = (int)$_POST['delete_index'];
        // Remove the row
        array_splice($devices, $delete_index, 1);
        write_db_file($headers, $devices);
        
        // Preserve filter parameters and position
        $params = $_GET;
        $params['position'] = max(0, $delete_index - 1);
        $query_string = http_build_query($params);
        header('Location: index.php' . ($query_string ? '?' . $query_string : ''));
        exit;
    }
}

// Debug output
error_log("Edit row index: " . $edit_row);

$show_import = isset($_GET['import']);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Database</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .menu {
            width: 100%;
            background: #333;
            margin-bottom: 30px;
            padding: 0;
        }
        .menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
        }
        .menu li {
            margin: 0 20px;
        }
        .menu a {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 16px 24px;
            font-size: 1.1em;
            transition: background 0.2s;
            border-radius: 4px 4px 0 0;
        }
        .menu a:hover {
            background: #2196F3;
        }
        .import-form {
            max-width: 400px;
            margin: 30px auto;
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15);
            text-align: center;
        }
        .import-form input[type="file"] {
            margin-bottom: 16px;
        }
        .import-form button {
            background: #4CAF50;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
        }
        .import-form .error {
            color: #c00;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-CL { color: green; }
        .status-NOCL { color: orange; }
        .status-OFF { color: red; }
        .action-btn { margin-right: 5px; }
        input.editcell { width: 100%; box-sizing: border-box; font-size: 1em; padding: 8px; }
        textarea.editcell { width: 100%; box-sizing: border-box; font-size: 1em; padding: 8px; resize: vertical; min-height: 40px; }
        .filterrow input { width: 98%; box-sizing: border-box; padding: 4px; font-size: 1em; }
    </style>
</head>
<body>
    <nav class="menu">
        <ul>
            <li><a href="?export=1">Export als sd.txt</a></li>
            <li><a href="?import=1">Import</a></li>
            <li><a href="?save=1">Save</a></li>
        </ul>
    </nav>
    <!--<h1>Device Database</h1>-->
    <?php if ($show_import): ?>
        <form class="import-form" method="post" enctype="multipart/form-data">
            <h2>Importiere db.txt Datei</h2>
            <?php if ($import_error): ?><div class="error"><?php echo $import_error; ?></div><?php endif; ?>
            <input type="file" name="importfile" accept=".txt" required><br>
            <button type="submit" name="import_db">Importieren</button>
        </form>
    <?php else: ?>
    <table id="devicetable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Hash</th>
                <th>Name</th>
                <th>IP</th>
                <th>OS Type</th>
                <th>Access</th>
                <th>Status</th>
                <th>Poll Interval</th>
                <th>Location ID</th>
                <th>Info</th>
                <th>Ticket ID</th>
                <th>Aktion</th>
            </tr>
            <tr class="filterrow">
                <td></td>
                <td><input type="text" placeholder="Filter Hash" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Name" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter IP" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter OS" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Access" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Status" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Poll" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Location" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Info" onkeyup="filterTable()"></td>
                <td><input type="text" placeholder="Filter Ticket" onkeyup="filterTable()"></td>
                <td></td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $i => $device): 
                $hash = substr(md5($device['name'] . $device['ip']), 0, 10);
                $isDuplicate = $hashes[$hash] > 1;
            ?>
            <tr>
                <?php if ($edit_row === $i): ?>
                <form method="post">
                    <input type="hidden" name="edit_index" value="<?php echo $i; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td style="<?php echo $isDuplicate ? 'background-color: #ffcccc;' : ''; ?>"><?php echo $hash; ?></td>
                    <td><input class="editcell" name="name" value="<?php echo htmlspecialchars($device['name']); ?>"></td>
                    <td><input class="editcell" name="ip" value="<?php echo htmlspecialchars($device['ip']); ?>"></td>
                    <td><input class="editcell" name="ostype" value="<?php echo htmlspecialchars($device['ostype']); ?>"></td>
                    <td><input class="editcell" name="access" value="<?php echo htmlspecialchars($device['access']); ?>"></td>
                    <td><input class="editcell" name="clear" value="<?php echo htmlspecialchars($device['clear']); ?>"></td>
                    <td><input class="editcell" name="poll" value="<?php echo htmlspecialchars($device['poll']); ?>"></td>
                    <td><input class="editcell" name="llid" value="<?php echo htmlspecialchars($device['llid']); ?>"></td>
                    <td><textarea class="editcell" name="info" rows="2"><?php echo htmlspecialchars($device['info']); ?></textarea></td>
                    <td><textarea class="editcell" name="tid" rows="2"><?php echo htmlspecialchars($device['tid']); ?></textarea></td>
                    <td>
                        <button type="submit" class="action-btn">Speichern</button>
                        <a href="javascript:void(0)" onclick="window.location.href = 'index.php' + (window.location.search.replace(/[?&]edit=\d+/, ''))" class="action-btn">Abbrechen</a>
                    </td>
                </form>
                <?php else: ?>
                    <td><?php echo $i + 1; ?></td>
                    <td style="<?php echo $isDuplicate ? 'background-color: #ffcccc;' : ''; ?>"><?php echo $hash; ?></td>
                    <td><?php echo htmlspecialchars($device['name']); ?></td>
                    <td><?php echo htmlspecialchars($device['ip']); ?></td>
                    <td><?php echo htmlspecialchars($device['ostype']); ?></td>
                    <td><?php echo htmlspecialchars($device['access']); ?></td>
                    <td class="status-<?php echo htmlspecialchars($device['clear']); ?>"><?php echo htmlspecialchars($device['clear']); ?></td>
                    <td><?php echo htmlspecialchars($device['poll']); ?> min</td>
                    <td><?php echo htmlspecialchars($device['llid']); ?></td>
                    <td><?php echo htmlspecialchars($device['info']); ?></td>
                    <td><?php echo htmlspecialchars($device['tid']); ?></td>
                    <td>
                        <button onclick="window.location.href = getEditUrl(<?php echo $i; ?>)" class="action-btn">Edit</button>
                        <button onclick="copyRow(<?php echo $i; ?>)" class="action-btn">Copy</button>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="delete_index" value="<?php echo $i; ?>">
                            <button type="submit" class="action-btn" style="color:red;">Löschen</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
    // Get filter values from URL parameters on page load
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Initialize filters from URL parameters
    window.onload = function() {
        var filters = document.querySelectorAll('.filterrow input');
        var filterNames = ['name', 'ip', 'os', 'access', 'status', 'poll', 'location', 'info', 'ticket'];
        filters.forEach((filter, index) => {
            var value = getUrlParameter('filter_' + filterNames[index]);
            if (value) {
                filter.value = value;
            }
        });
        filterTable(); // Apply filters on page load

        // Scroll to edited row if edit parameter exists
        var editIndex = getUrlParameter('edit');
        if (editIndex !== '') {
            var rows = document.querySelectorAll('#devicetable tbody tr');
            if (rows[editIndex]) {
                rows[editIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Scroll to position if specified
        var position = getUrlParameter('position');
        if (position !== '') {
            var rows = document.querySelectorAll('#devicetable tbody tr');
            if (rows[position]) {
                rows[position].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    function filterTable() {
        var table = document.getElementById('devicetable');
        var filters = table.querySelectorAll('.filterrow input');
        var rows = table.tBodies[0].rows;
        
        // Update URL with current filter values
        var params = new URLSearchParams(window.location.search);
        var filterNames = ['name', 'ip', 'os', 'access', 'status', 'poll', 'location', 'info', 'ticket'];
        filters.forEach((filter, index) => {
            if (filter.value) {
                params.set('filter_' + filterNames[index], filter.value);
            } else {
                params.delete('filter_' + filterNames[index]);
            }
        });
        
        // Update URL without reloading the page
        window.history.replaceState({}, '', '?' + params.toString());

        // Apply filters
        for (var i = 0; i < rows.length; i++) {
            var show = true;
            for (var j = 0; j < filters.length; j++) {
                var filter = filters[j].value.toLowerCase();
                var cell = rows[i].cells[j];
                if (filter && cell && cell.textContent.toLowerCase().indexOf(filter) === -1) {
                    show = false;
                    break;
                }
            }
            rows[i].style.display = show ? '' : 'none';
        }
    }

    // Function to create edit URL with current filters and position
    function getEditUrl(index) {
        var params = new URLSearchParams(window.location.search);
        var filterNames = ['name', 'ip', 'os', 'access', 'status', 'poll', 'location', 'info', 'ticket'];
        var filters = document.querySelectorAll('.filterrow input');
        filters.forEach((filter, index) => {
            if (filter.value) {
                params.set('filter_' + filterNames[index], filter.value);
            }
        });
        params.set('edit', index);
        params.set('position', index);
        return 'index.php?' + params.toString();
    }

    // Function to handle copy operation
    function copyRow(index) {
        // Get current filter parameters
        var params = new URLSearchParams(window.location.search);
        var filterNames = ['name', 'ip', 'os', 'access', 'status', 'poll', 'location', 'info', 'ticket'];
        var filters = document.querySelectorAll('.filterrow input');
        filters.forEach((filter, index) => {
            if (filter.value) {
                params.set('filter_' + filterNames[index], filter.value);
            }
        });
        
        // Add copy_index and position parameters
        params.set('copy_index', index);
        params.set('position', index);
        
        // Redirect to the same page with all parameters
        window.location.href = 'index.php?' + params.toString();
    }
    </script>
    <?php endif; ?>
</body>
</html> 