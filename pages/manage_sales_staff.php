<?php
require_once '../php/check_session.php';
require_permission('admin.access', [4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

function sales_staff_redirect_back(): void
{
    header('Location: manage_sales_staff.php');
    exit;
}

function sales_staff_set_flash(string $type, string $message): void
{
    $_SESSION['sales_staff_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_sales_staff') {
            $original_sales_code = trim($_POST['original_sales_code'] ?? '');
            $sales_code = strtoupper(trim($_POST['sales_code'] ?? ''));
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;

            if ($sales_code === '' || $full_name === '') {
                throw new RuntimeException('Please provide sales code and full name.');
            }

            if (!preg_match('/^[A-Z0-9_-]{1,8}$/', $sales_code)) {
                throw new RuntimeException('Sales code must be 1-8 characters and use only A-Z, 0-9, dash, or underscore.');
            }

            if ($original_sales_code !== '') {
                $stmt = $conn->prepare(
                    'UPDATE sales_staff
                     SET sales_code = ?, full_name = ?, phone = NULLIF(?, \'\'), active = ?
                     WHERE sales_code = ?'
                );
                $stmt->bind_param('sssis', $sales_code, $full_name, $phone, $active, $original_sales_code);
                $stmt->execute();
                if ($stmt->affected_rows < 0) {
                    throw new RuntimeException('Unable to update salesperson.');
                }
                $stmt->close();
                sales_staff_set_flash('success', 'Salesperson updated successfully.');
            } else {
                $check_stmt = $conn->prepare('SELECT sales_code FROM sales_staff WHERE sales_code = ? LIMIT 1');
                $check_stmt->bind_param('s', $sales_code);
                $check_stmt->execute();
                $exists = $check_stmt->get_result()->num_rows > 0;
                $check_stmt->close();

                if ($exists) {
                    throw new RuntimeException('This sales code already exists.');
                }

                $stmt = $conn->prepare(
                    'INSERT INTO sales_staff (sales_code, full_name, phone, active)
                     VALUES (?, ?, NULLIF(?, \'\'), ?)'
                );
                $stmt->bind_param('sssi', $sales_code, $full_name, $phone, $active);
                $stmt->execute();
                $stmt->close();
                sales_staff_set_flash('success', 'Salesperson created successfully.');
            }

            sales_staff_redirect_back();
        }

        if ($action === 'delete_sales_staff') {
            $sales_code = trim($_POST['delete_sales_code'] ?? '');
            if ($sales_code === '') {
                throw new RuntimeException('Sales code not found.');
            }

            $stmt = $conn->prepare('DELETE FROM sales_staff WHERE sales_code = ?');
            $stmt->bind_param('s', $sales_code);
            $stmt->execute();
            $stmt->close();

            sales_staff_set_flash('success', 'Salesperson deleted successfully.');
            sales_staff_redirect_back();
        }

        throw new RuntimeException('Unsupported action.');
    } catch (Throwable $e) {
        sales_staff_set_flash('danger', $e->getMessage());
        sales_staff_redirect_back();
    }
}

$flash = $_SESSION['sales_staff_flash'] ?? null;
unset($_SESSION['sales_staff_flash']);

$edit_sales_code = trim($_GET['edit'] ?? '');

$sales_staff_form = [
    'sales_code' => '',
    'full_name' => '',
    'phone' => '',
    'active' => 1,
];

if ($edit_sales_code !== '') {
    $stmt = $conn->prepare('SELECT sales_code, full_name, phone, active FROM sales_staff WHERE sales_code = ?');
    $stmt->bind_param('s', $edit_sales_code);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $sales_staff_form = $edit_result->fetch_assoc();
    }
    $stmt->close();
}

$sales_staff_rows = [];
$result = $conn->query(
    'SELECT sales_code, full_name, phone, active, created_at, updated_at
     FROM sales_staff
     ORDER BY active DESC, full_name ASC, sales_code ASC'
);

while ($result && ($row = $result->fetch_assoc())) {
    $sales_staff_rows[] = $row;
}

$summary = [
    'total' => count($sales_staff_rows),
    'active' => 0,
    'with_phone' => 0,
];

foreach ($sales_staff_rows as $row) {
    if ((int) $row['active'] === 1) {
        $summary['active']++;
    }
    if (!empty($row['phone'])) {
        $summary['with_phone']++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sales Staff</title>
    <meta name="theme-color" content="#dc2626">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(220, 38, 38, 0.12), transparent 34%),
                linear-gradient(180deg, #fff7f7 0%, #f8fafc 100%);
            min-height: 100vh;
        }
        .hero-card,
        .section-card,
        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
        }
        .hero-card {
            padding: 1.75rem;
            margin-bottom: 1.5rem;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(220, 38, 38, 0.08);
            color: #b91c1c;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .hero-title {
            font-size: clamp(1.9rem, 3vw, 2.6rem);
            font-weight: 700;
            color: #111827;
            margin-bottom: .5rem;
        }
        .hero-text {
            color: #475569;
            max-width: 760px;
            margin-bottom: 0;
        }
        .summary-card {
            padding: 1.1rem 1.25rem;
            height: 100%;
        }
        .summary-label {
            color: #64748b;
            font-size: .92rem;
            margin-bottom: .35rem;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: #0f172a;
        }
        .layout-grid {
            display: grid;
            grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
            gap: 1.5rem;
            align-items: start;
        }
        .section-card {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .section-heading h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        .muted-note {
            color: #64748b;
            font-size: .95rem;
        }
        .sticky-card {
            position: sticky;
            top: 1.25rem;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
        }
        .form-control,
        .custom-select {
            border-radius: 12px;
            min-height: 46px;
        }
        .custom-control-label {
            font-weight: 500;
        }
        .table-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            padding: .3rem .65rem;
            border-radius: 999px;
            font-size: .85rem;
            font-weight: 600;
        }
        .chip-active {
            background: rgba(22, 163, 74, 0.12);
            color: #15803d;
        }
        .chip-inactive {
            background: rgba(100, 116, 139, 0.14);
            color: #475569;
        }
        div.dataTables_wrapper div.dataTables_filter input,
        div.dataTables_wrapper div.dataTables_length select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            min-height: 38px;
        }
        @media (max-width: 991.98px) {
            .layout-grid {
                grid-template-columns: 1fr;
            }
            .sticky-card {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4 px-lg-4">
        <div class="hero-card">
            <div class="hero-badge">
                <i class="fas fa-user-tie"></i>
                Admin workspace
            </div>
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end">
                <div>
                    <h1 class="hero-title">Manage Sales Staff</h1>
                    <p class="hero-text">
                        Maintain the master list of salespeople used by CSSale code, including a direct phone number that can be shown in order details.
                    </p>
                </div>
                <div class="mt-3 mt-lg-0">
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-danger">
                        <i class="fas fa-arrow-left mr-2"></i>Back to dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">Total records</div>
                    <div class="summary-value"><?php echo number_format($summary['total']); ?></div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">Active records</div>
                    <div class="summary-value text-success"><?php echo number_format($summary['active']); ?></div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">With phone number</div>
                    <div class="summary-value text-primary"><?php echo number_format($summary['with_phone']); ?></div>
                </div>
            </div>
        </div>

        <div class="layout-grid">
            <div>
                <div class="section-card sticky-card">
                    <div class="section-heading">
                        <div>
                            <h4><?php echo $sales_staff_form['sales_code'] !== '' ? 'Edit salesperson' : 'Add salesperson'; ?></h4>
                            <div class="muted-note">The sales code is the master key matched against <code>cssale.code</code>.</div>
                        </div>
                    </div>
                    <form method="post">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_sales_staff">
                        <input type="hidden" name="original_sales_code" value="<?php echo htmlspecialchars($sales_staff_form['sales_code']); ?>">
                        <div class="form-group">
                            <label class="form-label" for="sales_code">Sales code</label>
                            <input
                                type="text"
                                class="form-control"
                                id="sales_code"
                                name="sales_code"
                                maxlength="8"
                                value="<?php echo htmlspecialchars($sales_staff_form['sales_code']); ?>"
                                placeholder="Example: A001"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="full_name"
                                name="full_name"
                                value="<?php echo htmlspecialchars($sales_staff_form['full_name']); ?>"
                                placeholder="Salesperson full name"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone number</label>
                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?php echo htmlspecialchars((string) ($sales_staff_form['phone'] ?? '')); ?>"
                                placeholder="08x-xxx-xxxx"
                            >
                        </div>
                        <div class="custom-control custom-switch mb-4">
                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="active"
                                name="active"
                                <?php echo !empty($sales_staff_form['active']) ? 'checked' : ''; ?>
                            >
                            <label class="custom-control-label" for="active">Active record</label>
                        </div>
                        <div class="d-flex flex-wrap" style="gap:.75rem;">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save mr-2"></i>Save
                            </button>
                            <?php if ($sales_staff_form['sales_code'] !== ''): ?>
                                <a href="manage_sales_staff.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-plus mr-2"></i>Create new
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="section-card">
                    <div class="section-heading">
                        <div>
                            <h4>Sales staff directory</h4>
                            <div class="muted-note">Search, sort, and maintain the master list used by order details.</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="salesStaffTable" class="table table-hover table-striped w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th>Sales code</th>
                                    <th>Full name</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_staff_rows as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['sales_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                                        <td>
                                            <span class="chip <?php echo (int) $row['active'] === 1 ? 'chip-active' : 'chip-inactive'; ?>">
                                                <?php echo (int) $row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($row['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['updated_at']))) : '-'; ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="?edit=<?php echo urlencode($row['sales_code']); ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <form method="post" class="delete-sales-staff-form mb-0">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete_sales_staff">
                                                    <input type="hidden" name="delete_sales_code" value="<?php echo htmlspecialchars($row['sales_code']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash-alt mr-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script>
        $(function () {
            $('#salesStaffTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[1, 'asc'], [0, 'asc']]
            });

            $('.delete-sales-staff-form').on('submit', function (event) {
                event.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'Delete this salesperson?',
                    text: 'The master record will be removed, but existing CSSale data will remain untouched.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
