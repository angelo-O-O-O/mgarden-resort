<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ════════════════════════════════════════════
// HANDLE POST ACTIONS
// ════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD FACILITY ──
    if ($action === 'add') {
        $name         = trim($_POST['facility_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $max_capacity = (int)($_POST['max_capacity'] ?? 0);
        $category     = trim($_POST['category'] ?? '');
        $availability = $_POST['availability'] === 'unavailable' ? 'unavailable' : 'available';
        $photo        = null;

        if (!empty($_FILES['photo']['tmp_name'])) {
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
        }

        $stmt = $db->prepare("
            INSERT INTO facilities (facility_name, description, max_capacity, category, availability, photo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssissa', $name, $description, $max_capacity, $category, $availability, $photo);
        $stmt->execute();
        $facilityId = $db->insert_id;

        // Insert pricing rows
        savePricing($db, $facilityId, $_POST['pricing'] ?? []);

        setFlash('success', 'Facility added successfully.');
        redirect(SITE_URL . '/receptionist/pages/facilities.php');
    }

    // ── EDIT FACILITY ──
    if ($action === 'edit') {
        $id           = (int)($_POST['facility_id'] ?? 0);
        $name         = trim($_POST['facility_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $max_capacity = (int)($_POST['max_capacity'] ?? 0);
        $category     = trim($_POST['category'] ?? '');
        $availability = $_POST['availability'] === 'unavailable' ? 'unavailable' : 'available';

        if (!empty($_FILES['photo']['tmp_name'])) {
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
            $stmt  = $db->prepare("UPDATE facilities SET facility_name=?, description=?, max_capacity=?, category=?, availability=?, photo=?, updated_at=NOW() WHERE facility_id=?");
            $stmt->bind_param('ssissai', $name, $description, $max_capacity, $category, $availability, $photo, $id);
        } else {
            $stmt = $db->prepare("UPDATE facilities SET facility_name=?, description=?, max_capacity=?, category=?, availability=?, updated_at=NOW() WHERE facility_id=?");
            $stmt->bind_param('ssissi', $name, $description, $max_capacity, $category, $availability, $id);
        }
        $stmt->execute();

        // Replace pricing rows
        $db->query("DELETE FROM pricing WHERE facility_id = $id");
        savePricing($db, $id, $_POST['pricing'] ?? []);

        setFlash('success', 'Facility updated successfully.');
        redirect(SITE_URL . '/receptionist/pages/facilities.php');
    }

    // ── DELETE FACILITY ──
    if ($action === 'delete') {
        $id = (int)($_POST['facility_id'] ?? 0);
        $db->query("DELETE FROM pricing WHERE facility_id = $id");
        $db->query("DELETE FROM facilities WHERE facility_id = $id");
        setFlash('success', 'Facility deleted.');
        redirect(SITE_URL . '/receptionist/pages/facilities.php');
    }
}

// ── HELPER: save pricing rows ──
function savePricing($db, $facilityId, $pricingRows) {
    if (empty($pricingRows)) return;
    $stmt = $db->prepare("
        INSERT INTO pricing (facility_id, rate_type, guest_type, base_price, exceed_rate)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($pricingRows as $row) {
        $rateType   = $row['rate_type']   ?? '';
        $guestType  = $row['guest_type']  ?? '';
        $basePrice  = (float)($row['base_price']  ?? 0);
        $exceedRate = (float)($row['exceed_rate'] ?? 0);
        if (!$rateType || !$guestType) continue;
        $stmt->bind_param('issdd', $facilityId, $rateType, $guestType, $basePrice, $exceedRate);
        $stmt->execute();
    }
}

// ── FETCH FACILITIES WITH PRICING ──
$facilities = $db->query("
    SELECT f.*,
           COUNT(DISTINCT r.reservation_id) AS booking_count
    FROM facilities f
    LEFT JOIN reservations r ON f.facility_id = r.facility_id AND r.status != 'cancelled'
    GROUP BY f.facility_id
    ORDER BY f.facility_name
")->fetch_all(MYSQLI_ASSOC);

// Fetch all pricing rows keyed by facility_id
$pricingAll = $db->query("SELECT * FROM pricing ORDER BY facility_id, rate_type, guest_type")->fetch_all(MYSQLI_ASSOC);
$pricingMap = [];
foreach ($pricingAll as $p) {
    $pricingMap[$p['facility_id']][] = $p;
}

function catIcon($cat) {
    $map = ['pool'=>'fa-solid fa-person-swimming','beach'=>'fa-solid fa-umbrella-beach',
            'accommodation'=>'fa-solid fa-bed','dining'=>'fa-solid fa-utensils',
            'spa'=>'fa-solid fa-spa','sports'=>'fa-solid fa-person-running',
            'event'=>'fa-solid fa-calendar-days','activity'=>'fa-solid fa-bullseye',
            'resort'=>'fa-solid fa-hotel'];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k => $icon) if (str_contains($c, $k)) return "<i class=\"{$icon}\"></i>";
    return '<i class="fa-solid fa-star"></i>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
  <div>
    <h1>Facilities</h1>
    <p>Manage resort facilities and their pricing</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-door-open"></i></div>
</div>

<!-- Header actions -->
<div class="section-header">
  <div>
    <h2>All Facilities</h2>
    <p><?= count($facilities) ?> facilit<?= count($facilities) != 1 ? 'ies' : 'y' ?> registered</p>
  </div>
  <button class="btn btn-primary" onclick="openAddModal()">
    <i class="fa-solid fa-plus"></i> Add Facility
  </button>
</div>

<!-- Facilities grid -->
<?php if (empty($facilities)): ?>
  <div class="table-card">
    <div class="empty-state">
      <i class="fa-solid fa-door-open"></i>
      <h3>No facilities yet</h3>
      <p>Add your first facility to get started.</p>
    </div>
  </div>
<?php else: ?>
  <div class="facilities-grid">
    <?php foreach ($facilities as $fac):
      $pricing = $pricingMap[$fac['facility_id']] ?? [];
    ?>
      <div class="facility-card">
        <!-- Card header with photo or gradient -->
        <div class="facility-card-header">
          <?php if (!empty($fac['photo'])): ?>
            <img
              src="data:image/jpeg;base64,<?= base64_encode($fac['photo']) ?>"
              alt="<?= e($fac['facility_name']) ?>"
              class="facility-card-photo"
            />
          <?php else: ?>
            <div class="facility-card-no-photo">
              <?= catIcon($fac['category']) ?>
            </div>
          <?php endif; ?>
          <div class="facility-card-overlay">
            <div class="facility-card-title"><?= e($fac['facility_name']) ?></div>
            <div class="facility-card-cat"><?= e($fac['category'] ?? 'General') ?></div>
          </div>
          <div class="facility-avail-badge <?= $fac['availability'] === 'available' ? 'avail-on' : 'avail-off' ?>">
            <?= $fac['availability'] === 'available' ? 'Available' : 'Unavailable' ?>
          </div>
        </div>

        <div class="facility-card-body">
          <?php if (!empty($fac['description'])): ?>
            <p class="facility-desc"><?= e(mb_strimwidth($fac['description'], 0, 100, '…')) ?></p>
          <?php endif; ?>

          <div class="facility-meta">
            <?php if ($fac['max_capacity']): ?>
              <span><i class="fa-solid fa-users"></i> <?= $fac['max_capacity'] ?> max</span>
            <?php endif; ?>
            <span><i class="fa-solid fa-calendar-check"></i> <?= $fac['booking_count'] ?> booking<?= $fac['booking_count'] != 1 ? 's' : '' ?></span>
          </div>

          <!-- Pricing table -->
          <?php if (!empty($pricing)): ?>
            <div class="pricing-mini">
              <div class="pricing-mini-head">
                <span>Rate Type</span><span>Guest</span><span>Base</span><span>Exceed</span>
              </div>
              <?php foreach ($pricing as $p): ?>
                <div class="pricing-mini-row">
                  <span class="status-badge badge-gray" style="font-size:0.68rem;"><?= ucfirst($p['rate_type']) ?></span>
                  <span><?= ucfirst($p['guest_type']) ?></span>
                  <span>₱<?= number_format($p['base_price'], 0) ?></span>
                  <span><?= $p['exceed_rate'] ? '₱'.number_format($p['exceed_rate'], 0) : '—' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="font-size:0.8rem;color:var(--gray-400);margin-top:10px;">No pricing set</p>
          <?php endif; ?>

          <!-- Actions -->
          <div class="facility-card-actions">
            <button
              class="btn btn-sm btn-outline"
              onclick="openEditModal(<?= htmlspecialchars(json_encode([
                'facility_id'  => $fac['facility_id'],
                'facility_name'=> $fac['facility_name'],
                'description'  => $fac['description'],
                'max_capacity' => $fac['max_capacity'],
                'category'     => $fac['category'],
                'availability' => $fac['availability'],
                'pricing'      => $pricing,
              ]), ENT_QUOTES) ?>)"
            >
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $fac['facility_id'] ?>, '<?= e($fac['facility_name']) ?>')">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ══ ADD/EDIT MODAL ══ -->
<div class="modal-backdrop" id="modalBackdrop" onclick="closeModal()"></div>
<div class="modal modal--wide" id="facilityModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title" id="modalTitle">Add Facility</h3>
      <p class="modal-subtitle" id="modalSubtitle">Fill in the facility details and pricing</p>
    </div>
    <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form method="POST" enctype="multipart/form-data" id="facilityForm">
      <input type="hidden" name="action" id="formAction" value="add"/>
      <input type="hidden" name="facility_id" id="formFacilityId" value=""/>

      <!-- Basic info -->
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Facility Name <span class="req">*</span></label>
          <input type="text" name="facility_name" id="fieldName" class="form-control form-control--plain" required placeholder="e.g. Pool A"/>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" id="fieldCategory" class="form-control form-control--plain">
            <option value="">Select category</option>
            <?php foreach (['Pool','Beach','Accommodation','Dining','Spa','Sports','Event','Activity','Resort'] as $cat): ?>
              <option value="<?= strtolower($cat) ?>"><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Capacity</label>
          <input type="number" name="max_capacity" id="fieldCapacity" class="form-control form-control--plain" min="0" placeholder="0"/>
        </div>
        <div class="form-group">
          <label class="form-label">Availability</label>
          <select name="availability" id="fieldAvailability" class="form-control form-control--plain">
            <option value="available">Available</option>
            <option value="unavailable">Unavailable</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" id="fieldDescription" class="form-control form-control--plain" rows="3" placeholder="Brief description of the facility…"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Photo <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
        <input type="file" name="photo" id="fieldPhoto" class="form-control form-control--plain" accept="image/*" onchange="previewPhoto(this)"/>
        <img id="photoPreview" src="" alt="Preview" style="display:none;margin-top:10px;max-height:140px;border-radius:var(--radius-sm);border:1px solid var(--gray-200);"/>
      </div>

      <!-- Pricing rows -->
      <div class="pricing-section">
        <div class="pricing-section-head">
          <span>Pricing</span>
          <button type="button" class="btn btn-sm btn-outline" onclick="addPricingRow()">
            <i class="fa-solid fa-plus"></i> Add Row
          </button>
        </div>
        <div class="pricing-row-head">
          <span>Rate Type</span><span>Guest Type</span><span>Base Price (₱)</span><span>Exceed Rate (₱)</span><span></span>
        </div>
        <div id="pricingRows"></div>
        <p id="noPricingMsg" style="font-size:0.82rem;color:var(--gray-400);text-align:center;padding:12px 0;">
          No pricing rows. Click "Add Row" to add one.
        </p>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <i class="fa-solid fa-save"></i> Save Facility
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ══ -->
<div class="modal-backdrop" id="deleteBackdrop" onclick="closeDeleteModal()"></div>
<div class="modal" id="deleteModal">
  <div class="modal-header">
    <h3 class="modal-title">Delete Facility</h3>
    <button class="modal-close" onclick="closeDeleteModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <p style="margin-bottom:20px;">Are you sure you want to delete <strong id="deleteFacilityName"></strong>?
    This will also remove all pricing rows. This action cannot be undone.</p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete"/>
      <input type="hidden" name="facility_id" id="deleteFacilityId"/>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── MODAL OPEN/CLOSE ──
function openAddModal() {
  document.getElementById('formAction').value     = 'add';
  document.getElementById('formFacilityId').value = '';
  document.getElementById('modalTitle').textContent    = 'Add Facility';
  document.getElementById('modalSubtitle').textContent = 'Fill in facility details and pricing';
  document.getElementById('facilityForm').reset();
  document.getElementById('photoPreview').style.display = 'none';
  document.getElementById('pricingRows').innerHTML = '';
  syncNoPricingMsg();
  showModal();
}

function openEditModal(data) {
  document.getElementById('formAction').value     = 'edit';
  document.getElementById('formFacilityId').value = data.facility_id;
  document.getElementById('modalTitle').textContent    = 'Edit Facility';
  document.getElementById('modalSubtitle').textContent = 'Update details for ' + data.facility_name;

  document.getElementById('fieldName').value         = data.facility_name || '';
  document.getElementById('fieldCategory').value     = data.category || '';
  document.getElementById('fieldCapacity').value     = data.max_capacity || '';
  document.getElementById('fieldAvailability').value = data.availability || 'available';
  document.getElementById('fieldDescription').value  = data.description || '';
  document.getElementById('photoPreview').style.display = 'none';
  document.getElementById('fieldPhoto').value = '';

  // Load pricing rows
  document.getElementById('pricingRows').innerHTML = '';
  (data.pricing || []).forEach(p => addPricingRow(p));
  syncNoPricingMsg();
  showModal();
}

function showModal() {
  document.getElementById('modalBackdrop').classList.add('show');
  document.getElementById('facilityModal').classList.add('show');
}
function closeModal() {
  document.getElementById('modalBackdrop').classList.remove('show');
  document.getElementById('facilityModal').classList.remove('show');
}

function confirmDelete(id, name) {
  document.getElementById('deleteFacilityId').value   = id;
  document.getElementById('deleteFacilityName').textContent = name;
  document.getElementById('deleteBackdrop').classList.add('show');
  document.getElementById('deleteModal').classList.add('show');
}
function closeDeleteModal() {
  document.getElementById('deleteBackdrop').classList.remove('show');
  document.getElementById('deleteModal').classList.remove('show');
}

// ── PRICING ROWS ──
let pricingRowCount = 0;

function addPricingRow(data = {}) {
  const idx      = pricingRowCount++;
  const rateTypes  = ['daytime','overnight'];
  const guestTypes = ['kids','adults','general'];

  const row = document.createElement('div');
  row.className = 'pricing-input-row';
  row.id = 'pricingRow_' + idx;

  row.innerHTML = `
    <select name="pricing[${idx}][rate_type]" class="form-control form-control--plain form-control--sm">
      ${rateTypes.map(t => `<option value="${t}" ${data.rate_type===t?'selected':''}>${t.charAt(0).toUpperCase()+t.slice(1)}</option>`).join('')}
    </select>
    <select name="pricing[${idx}][guest_type]" class="form-control form-control--plain form-control--sm">
      ${guestTypes.map(t => `<option value="${t}" ${data.guest_type===t?'selected':''}>${t.charAt(0).toUpperCase()+t.slice(1)}</option>`).join('')}
    </select>
    <input type="number" name="pricing[${idx}][base_price]" class="form-control form-control--plain form-control--sm"
           placeholder="0.00" step="0.01" min="0" value="${data.base_price||''}"/>
    <input type="number" name="pricing[${idx}][exceed_rate]" class="form-control form-control--plain form-control--sm"
           placeholder="0.00" step="0.01" min="0" value="${data.exceed_rate||''}"/>
    <button type="button" class="btn btn-sm btn-danger" onclick="removePricingRow('pricingRow_${idx}')">
      <i class="fa-solid fa-times"></i>
    </button>
  `;
  document.getElementById('pricingRows').appendChild(row);
  syncNoPricingMsg();
}

function removePricingRow(id) {
  document.getElementById(id)?.remove();
  syncNoPricingMsg();
}

function syncNoPricingMsg() {
  const hasRows = document.getElementById('pricingRows').children.length > 0;
  document.getElementById('noPricingMsg').style.display = hasRows ? 'none' : 'block';
}

// ── PHOTO PREVIEW ──
function previewPhoto(input) {
  const preview = document.getElementById('photoPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>