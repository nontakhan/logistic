<?php
// Run with C:\xampp\php\php.exe tests\analytics_salesman_filter_test.php
// Uses only a disposable local database; auth is stubbed, not tested here.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '');
$schema = 'logistic_analytics_test_' . bin2hex(random_bytes(6));
$sandbox = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $schema;
$failures = 0;
$checks = 0;

function check_equal($actual, $expected, string $message): void
{
    global $failures, $checks;
    $checks++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL: $message; expected " . json_encode($expected) . ', got ' . json_encode($actual) . PHP_EOL;
    }
}

function request_analytics(array $query): array
{
    global $sandbox;
    $process = proc_open([PHP_BINARY, $sandbox . '/request.php', json_encode($query)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $response = json_decode($output, true);
    if ($exit !== 0 || !is_array($response) || $errors !== '') {
        throw new RuntimeException('Endpoint failed: ' . $errors . $output);
    }
    return $response;
}

try {
    $db->query("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->select_db($schema);
    $db->set_charset('utf8mb4');
    $statements = [
        'CREATE TABLE orders (order_id INT PRIMARY KEY, cssale_docno VARCHAR(20), order_date DATE, transport_origin_id INT, assigned_vehicle_id INT, assigned_staff_id INT, customer_address_origin_id INT, status VARCHAR(50))',
        'CREATE TABLE cssale (docno VARCHAR(20) PRIMARY KEY, code VARCHAR(20), lname VARCHAR(100), custname VARCHAR(100))',
        'CREATE TABLE transport_origins (transport_origin_id INT PRIMARY KEY, origin_name VARCHAR(50))',
        'CREATE TABLE vehicles (vehicle_id INT PRIMARY KEY, vehicle_name VARCHAR(50))',
        'CREATE TABLE staff (staff_id INT PRIMARY KEY, staff_name VARCHAR(50))',
        'CREATE TABLE origin (id INT PRIMARY KEY, province VARCHAR(50), amphoe VARCHAR(50), tambon VARCHAR(50), moo VARCHAR(50), mooban VARCHAR(50))',
        "INSERT INTO transport_origins VALUES (1, 'สาขาหนึ่ง'), (2, 'สาขาสอง')",
        "INSERT INTO vehicles VALUES (1, 'รถกระบะ')",
        "INSERT INTO staff VALUES (1, 'คนขับทดสอบ')",
        "INSERT INTO origin VALUES (1, 'จังหวัดหนึ่ง', 'อำเภอหนึ่ง', 'ตำบลหนึ่ง', '1', 'หมู่บ้านหนึ่ง'), (2, 'จังหวัดสอง', 'อำเภอสอง', 'ตำบลสอง', '2', 'หมู่บ้านสอง')",
        "INSERT INTO cssale VALUES ('A1', '001', 'สมชาย', 'ลูกค้า ก'), ('A2', '001', 'สมชาย', 'ลูกค้า ก'), ('B1', '002', 'สมหญิง <ทดสอบ>', 'ลูกค้า ข'), ('C1', '003', 'สาขาสอง', 'ลูกค้า ค'), ('Z1', '0', 'รหัสศูนย์', 'ลูกค้า ง')",
        "INSERT INTO orders VALUES (1, 'A1', '2026-10-01', 1, 1, 1, 1, 'ส่งของแล้ว'), (2, 'A2', '2026-09-15', 1, 1, 1, 1, 'รอรับเรื่อง'), (3, 'B1', '2026-10-02', 1, 1, 1, 1, 'ยกเลิก'), (4, 'C1', '2026-10-03', 2, 1, 1, 2, 'ส่งของแล้ว'), (5, NULL, '2026-10-04', 1, 1, 1, 1, 'รอรับเรื่อง'), (6, 'Z1', '2026-10-05', 1, 1, 1, 1, 'รอรับเรื่อง')",
    ];
    foreach ($statements as $sql) {
        $db->query($sql);
    }

    mkdir($sandbox);
    copy(__DIR__ . '/../php/get_analytics_data.php', $sandbox . '/get_analytics_data.php');
    file_put_contents($sandbox . '/check_session.php', <<<'PHP'
<?php
function is_logged_in() { return true; }
function has_permission(...$args) { return true; }
function should_limit_to_assigned_origin() { return !empty($_GET['_limited']); }
function current_assigned_transport_origin_id() { return 1; }
function user_can_filter_all_origins() { return true; }
PHP
    );
    file_put_contents($sandbox . '/db_connect.php', '<?php $conn = new mysqli("127.0.0.1", "root", "", ' . var_export($schema, true) . '); $conn->set_charset("utf8mb4");');
    file_put_contents($sandbox . '/request.php', '<?php $_GET = json_decode($argv[1], true); require __DIR__ . "/get_analytics_data.php";');

    $cases = [
        ['all employees', [], 6],
        ['one employee', ['salesman_codes' => ['001']], 2],
        ['multiple employees use OR', ['salesman_codes' => ['001', '002']], 3],
        ['empty selection includes unlinked bills', ['salesman_codes' => []], 6],
        ['duplicate employee does not multiply rows', ['salesman_codes' => ['001', '001']], 2],
        ['leading zero is preserved', ['salesman_codes' => ['1']], 0],
        ['zero code is preserved', ['salesman_codes' => ['0']], 1],
        ['unknown employee returns no orders', ['salesman_codes' => ['999']], 0],
        ['SQL input remains a bound value', ['salesman_codes' => ["001') OR 1=1 --"]], 0],
        ['date and employee filters intersect', ['salesman_codes' => ['001', '002'], 'date_start' => '2026-10-01', 'date_end' => '2026-10-31'], 2],
        ['branch and employee filters intersect', ['salesman_codes' => ['001', '003'], 'transport_origin' => '2'], 1],
        ['assigned branch still applies', ['salesman_codes' => ['001', '003'], '_limited' => true], 2],
        ['requested branch cannot bypass assigned branch', ['salesman_codes' => ['003'], 'transport_origin' => '2', '_limited' => true], 0],
        ['customer and employee filters intersect', ['salesman_codes' => ['001', '002'], 'customer' => 'ลูกค้า ข'], 1],
    ];
    foreach ($cases as [$name, $query, $expected]) {
        $response = request_analytics($query);
        check_equal($response['status'], 'success', "$name response");
        check_equal($response['data']['order_stats']['total'] ?? null, $expected, $name);
    }

    $response = request_analytics(['salesman_codes' => ['001', '002'], 'date_start' => '2026-09-01', 'date_end' => '2026-10-31']);
    foreach (['status_distribution', 'branch_rankings', 'vehicle_types', 'top_provinces', 'top_amphoes', 'top_tambons', 'location_rankings', 'customer_rankings', 'monthly_summary'] as $key) {
        check_equal(array_sum(array_column($response['data'][$key], 'value')), 3, "$key uses the employee selection");
    }
    check_equal(array_sum(array_column($response['data']['driver_performance'], 'count')), 3, 'driver table uses employee selection');
    check_equal($response['data']['order_stats']['delivered'], 1, 'selected delivered count');
    check_equal($response['data']['order_stats']['cancelled'], 1, 'selected cancelled count');

    foreach (['001', [['001']]] as $invalid) {
        $response = request_analytics(['salesman_codes' => $invalid]);
        check_equal($response['status'], 'error', 'malformed selection is rejected');
    }

    $options = request_analytics(['action' => 'get_filter_options']);
    $employees = $options['data']['salesmen'] ?? [];
    $codes = array_column($employees, 'code');
    sort($codes);
    check_equal($codes, ['0', '001', '002', '003'], 'employee options are unique and preserve codes');
    $options = request_analytics(['action' => 'get_filter_options', '_limited' => true]);
    $codes = array_column($options['data']['salesmen'] ?? [], 'code');
    sort($codes);
    check_equal($codes, ['0', '001', '002'], 'employee options respect assigned branch');
} finally {
    // Only remove this run's exact generated database and sandbox files.
    if (preg_match('/^logistic_analytics_test_[a-f0-9]{12}$/', $schema)) {
        $db->query("DROP DATABASE IF EXISTS `$schema`");
    }
    foreach (['get_analytics_data.php', 'check_session.php', 'db_connect.php', 'request.php'] as $file) {
        if (is_file($sandbox . '/' . $file)) {
            unlink($sandbox . '/' . $file);
        }
    }
    if (is_dir($sandbox)) {
        rmdir($sandbox);
    }
    $db->close();
}
echo "$checks checks, $failures failures" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
