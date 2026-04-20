<?php
require_once __DIR__ . '/../includes/config.php';
requireReceptionistLogin();

$db = getDB();

// ── Active tab ──
$activeTab = $_GET['tab'] ?? 'facilities';
if (!in_array($activeTab, ['facilities','addons'])) $activeTab = 'facilities';

// ════════════════════════════════════════════
// FACILITY ACTIONS
// ════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD FACILITY
    if ($action === 'add_facility') {
        $name         = trim($_POST['facility_name'] ?? '');
        $description  = trim($_POST['description']   ?? '');
        $max_capacity = (int)($_POST['max_capacity']  ?? 0);
        $category     = trim($_POST['category']       ?? '');
        $availability = ($_POST['availability'] ?? '') === 'unavailable' ? 'unavailable' : 'available';
        
        if (!empty($_FILES['photo']['tmp_name'])) {
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
            $stmt = $db->prepare("INSERT INTO facilities (facility_name,description,max_capacity,category,availability,photo) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssisss', $name, $description, $max_capacity, $category, $availability, $photo);
        } else {
            $stmt = $db->prepare("INSERT INTO facilities (facility_name,description,max_capacity,category,availability) VALUES (?,?,?,?,?)");
            $stmt->bind_param('ssis', $name, $description, $max_capacity, $category, $availability);
        }
        $stmt->execute();
        $fid = $db->insert_id;
        savePricing($db, $fid, $_POST['pricing'] ?? []);
        setFlash('success','Facility added successfully.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=facilities');
    }

    // EDIT FACILITY
    if ($action === 'edit_facility') {
        $id           = (int)($_POST['facility_id']   ?? 0);
        $name         = trim($_POST['facility_name']  ?? '');
        $description  = trim($_POST['description']    ?? '');
        $max_capacity = (int)($_POST['max_capacity']  ?? 0);
        $category     = trim($_POST['category']       ?? '');
        $availability = ($_POST['availability'] ?? '') === 'unavailable' ? 'unavailable' : 'available';

        if (!empty($_FILES['photo']['tmp_name'])) {
            $photo = file_get_contents($_FILES['photo']['tmp_name']);
            $stmt  = $db->prepare("UPDATE facilities SET facility_name=?,description=?,max_capacity=?,category=?,availability=?,photo=?,updated_at=NOW() WHERE facility_id=?");
            $stmt->bind_param('ssisssi', $name,$description,$max_capacity,$category,$availability,$photo,$id);
        } else {
            $stmt = $db->prepare("UPDATE facilities SET facility_name=?,description=?,max_capacity=?,category=?,availability=?,updated_at=NOW() WHERE facility_id=?");
            $stmt->bind_param('ssissi', $name,$description,$max_capacity,$category,$availability,$id);
        }
        $stmt->execute();
        $db->query("DELETE FROM pricing WHERE facility_id=$id");
        savePricing($db, $id, $_POST['pricing'] ?? []);
        setFlash('success','Facility updated.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=facilities');
    }

    // DELETE FACILITY
    if ($action === 'delete_facility') {
        $id = (int)($_POST['facility_id'] ?? 0);
        $db->query("DELETE FROM pricing WHERE facility_id=$id");
        $db->query("DELETE FROM facilities WHERE facility_id=$id");
        setFlash('success','Facility deleted.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=facilities');
    }

    // ADD ADDON
    if ($action === 'add_addon') {
        $name        = trim($_POST['addon_name']        ?? '');
        $desc        = trim($_POST['addon_description'] ?? '');
        $limit       = (int)($_POST['limit_per_reservation'] ?? 0);
        $price       = (float)($_POST['addon_price']    ?? 0);
        $stmt = $db->prepare("INSERT INTO addons (addon_name,addon_description,limit_per_reservation,addon_price) VALUES (?,?,?,?)");
        $stmt->bind_param('ssid', $name,$desc,$limit,$price);
        $stmt->execute();
        setFlash('success','Add-on added.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=addons');
    }

    // EDIT ADDON
    if ($action === 'edit_addon') {
        $id    = (int)($_POST['addon_id']              ?? 0);
        $name  = trim($_POST['addon_name']              ?? '');
        $desc  = trim($_POST['addon_description']       ?? '');
        $limit = (int)($_POST['limit_per_reservation']  ?? 0);
        $price = (float)($_POST['addon_price']          ?? 0);
        $stmt  = $db->prepare("UPDATE addons SET addon_name=?,addon_description=?,limit_per_reservation=?,addon_price=?,modified_at=NOW() WHERE addon_id=?");
        $stmt->bind_param('ssidi', $name, $desc, $limit, $price, $id);
        $stmt->execute();
        setFlash('success','Add-on updated.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=addons');
    }

    // DELETE ADDON
    if ($action === 'delete_addon') {
        $id = (int)($_POST['addon_id'] ?? 0);
        $db->query("DELETE FROM addons WHERE addon_id=$id");
        setFlash('success','Add-on deleted.');
        redirect(SITE_URL.'/receptionist/pages/facilities.php?tab=addons');
    }
}

function savePricing($db, $facilityId, $rows) {
    if (empty($rows)) return;
    $stmt = $db->prepare("INSERT INTO pricing (facility_id,rate_type,guest_type,base_price,exceed_rate) VALUES (?,?,?,?,?)");
    foreach ($rows as $row) {
        $rt = $row['rate_type'] ?? ''; $gt = $row['guest_type'] ?? '';
        $bp = (float)($row['base_price'] ?? 0); $er = (float)($row['exceed_rate'] ?? 0);
        if (!$rt || !$gt) continue;
        $stmt->bind_param('issdd', $facilityId,$rt,$gt,$bp,$er);
        $stmt->execute();
    }
}

// ── FETCH DATA ──
$facilities = $db->query("
    SELECT f.*, COUNT(DISTINCT r.reservation_id) AS booking_count
    FROM facilities f
    LEFT JOIN reservations r ON f.facility_id=r.facility_id AND r.status!='cancelled'
    GROUP BY f.facility_id ORDER BY f.facility_name
")->fetch_all(MYSQLI_ASSOC);

$allPricing = $db->query("SELECT * FROM pricing ORDER BY facility_id,rate_type,guest_type")->fetch_all(MYSQLI_ASSOC);
$pricingMap = [];
foreach ($allPricing as $p) $pricingMap[$p['facility_id']][] = $p;

$addons = $db->query("SELECT * FROM addons ORDER BY addon_name")->fetch_all(MYSQLI_ASSOC);

$CATEGORIES = ['room'=>'Room','family room'=>'Family Room','pool'=>'Pool','cottage'=>'Cottage'];

function catIcon($cat) {
    $map = ['pool'=>'fa-solid fa-person-swimming','room'=>'fa-solid fa-bed',
            'family room'=>'fa-solid fa-people-roof','cottage'=>'fa-solid fa-house-chimney'];
    $c = strtolower(trim($cat ?? ''));
    foreach ($map as $k=>$icon) if (str_contains($c,$k)) return "<i class=\"{$icon}\"></i>";
    return '<i class="fa-solid fa-star"></i>';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
  <div>
    <h1>Facilities &amp; Add-ons</h1>
    <p>Manage resort facilities, pricing, and add-on services</p>
  </div>
  <div class="page-banner-icon"><i class="fa-solid fa-door-open"></i></div>
</div>

<!-- Page tabs -->
<div class="page-tabs">
  <a href="?tab=facilities" class="page-tab <?= $activeTab==='facilities'?'active':'' ?>">
    <i class="fa-solid fa-door-open"></i> Facilities
    <span class="filter-tab-count"><?= count($facilities) ?></span>
  </a>
  <a href="?tab=addons" class="page-tab <?= $activeTab==='addons'?'active':'' ?>">
    <i class="fa-solid fa-puzzle-piece"></i> Add-ons
    <span class="filter-tab-count"><?= count($addons) ?></span>
  </a>
</div>

<!-- ═══════════════════════════════
     FACILITIES TAB
═══════════════════════════════ -->
<?php if ($activeTab === 'facilities'): ?>

<div class="section-header" style="margin-top:20px;">
  <div>
    <h2>All Facilities</h2>
    <p><?= count($facilities) ?> facilit<?= count($facilities)!=1?'ies':'y' ?> registered</p>
  </div>
  <button class="btn btn-primary" onclick="openAddFacility()">
    <i class="fa-solid fa-plus"></i> Add Facility
  </button>
</div>

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
        <div class="facility-card-header">
          <?php if (!empty($fac['photo'])): ?>
            <img src="data:image/jpeg;base64,<?= base64_encode($fac['photo']) ?>" alt="<?= e($fac['facility_name']) ?>" class="facility-card-photo"/>
          <?php else: ?>
            <div class="facility-card-no-photo"><?= catIcon($fac['category']) ?></div>
          <?php endif; ?>
          <div class="facility-card-overlay">
            <div class="facility-card-title"><?= e($fac['facility_name']) ?></div>
            <div class="facility-card-cat"><?= e($CATEGORIES[strtolower($fac['category']??'')] ?? ucfirst($fac['category']??'')) ?></div>
          </div>
          <div class="facility-avail-badge <?= $fac['availability']==='available'?'avail-on':'avail-off' ?>">
            <?= $fac['availability']==='available'?'Available':'Unavailable' ?>
          </div>
        </div>
        <div class="facility-card-body">
          <?php if (!empty($fac['description'])): ?>
            <p class="facility-desc"><?= e(mb_strimwidth($fac['description'],0,100,'…')) ?></p>
          <?php endif; ?>
          <div class="facility-meta">
            <?php if ($fac['max_capacity']): ?>
              <span><i class="fa-solid fa-users"></i> <?= $fac['max_capacity'] ?> max</span>
            <?php endif; ?>
            <span><i class="fa-solid fa-calendar-check"></i> <?= $fac['booking_count'] ?> booking<?= $fac['booking_count']!=1?'s':'' ?></span>
          </div>
          <?php if (!empty($pricing)): ?>
            <div class="pricing-mini">
              <div class="pricing-mini-head"><span>Rate</span><span>Guest</span><span>Base</span><span>Exceed</span></div>
              <?php foreach ($pricing as $p): ?>
                <div class="pricing-mini-row">
                  <span class="status-badge badge-gray" style="font-size:0.65rem;"><?= ucfirst($p['rate_type']) ?></span>
                  <span><?= ucfirst($p['guest_type']) ?></span>
                  <span>₱<?= number_format($p['base_price'],0) ?></span>
                  <span><?= $p['exceed_rate']?'₱'.number_format($p['exceed_rate'],0):'—' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="font-size:0.78rem;color:var(--gray-300);margin:8px 0;">No pricing set</p>
          <?php endif; ?>
          <div class="facility-card-actions">
            <button class="btn btn-sm btn-outline" onclick='openEditFacility(<?= htmlspecialchars(json_encode([
              "facility_id"  => $fac["facility_id"],
              "facility_name"=> $fac["facility_name"],
              "description"  => $fac["description"],
              "max_capacity" => $fac["max_capacity"],
              "category"     => strtolower($fac["category"]??''),
              "availability" => $fac["availability"],
              "pricing"      => $pricing,
            ]),ENT_QUOTES) ?>)'>
              <i class="fa-solid fa-pen"></i> Edit
            </button>
            <button class="btn btn-sm btn-danger" onclick="confirmDeleteFacility(<?= $fac['facility_id'] ?>,'<?= e($fac['facility_name']) ?>')">
              <i class="fa-solid fa-trash"></i> Delete
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php endif; ?>

<!-- ═══════════════════════════════
     ADDONS TAB
═══════════════════════════════ -->
<?php if ($activeTab === 'addons'): ?>

<div class="section-header" style="margin-top:20px;">
  <div>
    <h2>Add-on Services</h2>
    <p><?= count($addons) ?> add-on<?= count($addons)!=1?'s':'' ?> available</p>
  </div>
  <button class="btn btn-primary" onclick="openAddAddon()">
    <i class="fa-solid fa-plus"></i> Add Add-on
  </button>
</div>

<?php if (empty($addons)): ?>
  <div class="table-card">
    <div class="empty-state">
      <i class="fa-solid fa-puzzle-piece"></i>
      <h3>No add-ons yet</h3>
      <p>Add your first add-on service.</p>
    </div>
  </div>
<?php else: ?>
  <div class="table-card">
    <table>
      <thead>
        <tr><th>Add-on Name</th><th>Description</th><th>Limit / Reservation</th><th>Price</th><th>Added</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($addons as $a): ?>
          <tr>
            <td><strong><?= e($a['addon_name']) ?></strong></td>
            <td><span style="font-size:0.85rem;color:var(--gray-500);"><?= e($a['addon_description']??'—') ?></span></td>
            <td><?= $a['limit_per_reservation'] ? $a['limit_per_reservation'].' per booking' : '<span style="color:var(--gray-300);">No limit</span>' ?></td>
            <td><strong>₱<?= number_format($a['addon_price'],2) ?></strong></td>
            <td><small style="color:var(--gray-400);"><?= date('M d, Y', strtotime($a['created_at'])) ?></small></td>
            <td>
              <div class="action-buttons">
                <button class="btn btn-sm btn-outline" onclick='openEditAddon(<?= htmlspecialchars(json_encode([
                  "addon_id"              => $a["addon_id"],
                  "addon_name"            => $a["addon_name"],
                  "addon_description"     => $a["addon_description"],
                  "limit_per_reservation" => $a["limit_per_reservation"],
                  "addon_price"           => $a["addon_price"],
                ]),ENT_QUOTES) ?>)'>
                  <i class="fa-solid fa-pen"></i> Edit
                </button>
                <button class="btn btn-sm btn-danger" onclick="confirmDeleteAddon(<?= $a['addon_id'] ?>,'<?= e($a['addon_name']) ?>')">
                  <i class="fa-solid fa-trash"></i> Delete
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php endif; ?>

<!-- ═══ FACILITY MODAL ═══ -->
<div class="modal-backdrop" id="facilityBackdrop" onclick="closeFacilityModal()"></div>
<div class="modal modal--wide" id="facilityModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title" id="facilityModalTitle">Add Facility</h3>
      <p class="modal-subtitle">Fill in facility details and pricing</p>
    </div>
    <button class="modal-close" onclick="closeFacilityModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form method="POST" enctype="multipart/form-data" id="facilityForm">
      <input type="hidden" name="action" id="facAction" value="add_facility"/>
      <input type="hidden" name="facility_id" id="facId"/>

      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Facility Name <span class="req">*</span></label>
          <input type="text" name="facility_name" id="facName" class="form-control form-control--plain" required placeholder="e.g. Pool A"/>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" id="facCategory" class="form-control form-control--plain">
            <option value="">Select category</option>
            <option value="room">Room</option>
            <option value="family room">Family Room</option>
            <option value="pool">Pool</option>
            <option value="cottage">Cottage</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Max Capacity</label>
          <input type="number" name="max_capacity" id="facCapacity" class="form-control form-control--plain" min="0" placeholder="0"/>
        </div>
        <div class="form-group">
          <label class="form-label">Availability</label>
          <select name="availability" id="facAvailability" class="form-control form-control--plain">
            <option value="available">Available</option>
            <option value="unavailable">Unavailable</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" id="facDescription" class="form-control form-control--plain" rows="3" placeholder="Brief description…"></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Photo <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
        <input type="file" name="photo" id="facPhoto" class="form-control form-control--plain" accept="image/*" onchange="previewFacPhoto(this)"/>
        <img id="facPhotoPreview" src="" style="display:none;margin-top:10px;max-height:130px;border-radius:var(--radius-sm);border:1px solid var(--gray-200);" alt="Preview"/>
      </div>

      <!-- Pricing -->
      <div class="pricing-section">
        <div class="pricing-section-head">
          <span>Pricing Rows</span>
          <button type="button" class="btn btn-sm btn-outline" onclick="addPricingRow()">
            <i class="fa-solid fa-plus"></i> Add Row
          </button>
        </div>
        <div class="pricing-row-head">
          <span>Rate Type</span><span>Guest Type</span><span>Base Price (₱)</span><span>Exceed Rate (₱)</span><span></span>
        </div>
        <div id="pricingRows"></div>
        <p id="noPricingMsg" style="font-size:0.82rem;color:var(--gray-400);text-align:center;padding:12px;">No rows yet. Click "Add Row".</p>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeFacilityModal()">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Facility</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ DELETE FACILITY MODAL ═══ -->
<div class="modal-backdrop" id="delFacBackdrop" onclick="closeDelFac()"></div>
<div class="modal" id="delFacModal">
  <div class="modal-header">
    <h3 class="modal-title">Delete Facility</h3>
    <button class="modal-close" onclick="closeDelFac()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <p style="margin-bottom:20px;">Delete <strong id="delFacName"></strong>? This also removes all pricing rows and cannot be undone.</p>
    <form method="POST" id="delFacForm">
      <input type="hidden" name="action" value="delete_facility"/>
      <input type="hidden" name="facility_id" id="delFacId"/>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeDelFac()">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ ADDON MODAL ═══ -->
<div class="modal-backdrop" id="addonBackdrop" onclick="closeAddonModal()"></div>
<div class="modal" id="addonModal">
  <div class="modal-header">
    <div>
      <h3 class="modal-title" id="addonModalTitle">Add Add-on</h3>
      <p class="modal-subtitle">Fill in the add-on service details</p>
    </div>
    <button class="modal-close" onclick="closeAddonModal()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form method="POST" id="addonForm">
      <input type="hidden" name="action" id="addonAction" value="add_addon"/>
      <input type="hidden" name="addon_id" id="addonId"/>

      <div class="form-group">
        <label class="form-label">Add-on Name <span class="req">*</span></label>
        <input type="text" name="addon_name" id="addonName" class="form-control form-control--plain" required placeholder="e.g. Extra Towel Set"/>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="addon_description" id="addonDesc" class="form-control form-control--plain" rows="2" placeholder="Brief description…"></textarea>
      </div>
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Price (₱) <span class="req">*</span></label>
          <input type="number" name="addon_price" id="addonPrice" class="form-control form-control--plain" min="0" step="0.01" placeholder="0.00" required/>
        </div>
        <div class="form-group">
          <label class="form-label">Limit per Reservation</label>
          <input type="number" name="limit_per_reservation" id="addonLimit" class="form-control form-control--plain" min="0" placeholder="0 = no limit"/>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeAddonModal()">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Add-on</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ DELETE ADDON MODAL ═══ -->
<div class="modal-backdrop" id="delAddonBackdrop" onclick="closeDelAddon()"></div>
<div class="modal" id="delAddonModal">
  <div class="modal-header">
    <h3 class="modal-title">Delete Add-on</h3>
    <button class="modal-close" onclick="closeDelAddon()"><i class="fa-solid fa-times"></i></button>
  </div>
  <div class="modal-body">
    <p style="margin-bottom:20px;">Delete add-on <strong id="delAddonName"></strong>? This cannot be undone.</p>
    <form method="POST" id="delAddonForm">
      <input type="hidden" name="action" value="delete_addon"/>
      <input type="hidden" name="addon_id" id="delAddonId"/>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="closeDelAddon()">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── FACILITY MODAL ──
function openAddFacility() {
  document.getElementById('facAction').value = 'add_facility';
  document.getElementById('facId').value     = '';
  document.getElementById('facilityModalTitle').textContent = 'Add Facility';
  document.getElementById('facilityForm').reset();
  document.getElementById('facPhotoPreview').style.display = 'none';
  document.getElementById('pricingRows').innerHTML = '';
  syncNoPricing();
  document.getElementById('facilityBackdrop').classList.add('show');
  document.getElementById('facilityModal').classList.add('show');
}
function openEditFacility(data) {
  document.getElementById('facAction').value        = 'edit_facility';
  document.getElementById('facId').value            = data.facility_id;
  document.getElementById('facilityModalTitle').textContent = 'Edit Facility';
  document.getElementById('facName').value          = data.facility_name || '';
  document.getElementById('facCategory').value      = data.category      || '';
  document.getElementById('facCapacity').value      = data.max_capacity  || '';
  document.getElementById('facAvailability').value  = data.availability  || 'available';
  document.getElementById('facDescription').value   = data.description   || '';
  document.getElementById('facPhoto').value         = '';
  document.getElementById('facPhotoPreview').style.display = 'none';
  document.getElementById('pricingRows').innerHTML  = '';
  (data.pricing || []).forEach(p => addPricingRow(p));
  syncNoPricing();
  document.getElementById('facilityBackdrop').classList.add('show');
  document.getElementById('facilityModal').classList.add('show');
}
function closeFacilityModal() {
  document.getElementById('facilityBackdrop').classList.remove('show');
  document.getElementById('facilityModal').classList.remove('show');
}
function confirmDeleteFacility(id, name) {
  document.getElementById('delFacId').value              = id;
  document.getElementById('delFacName').textContent      = name;
  document.getElementById('delFacBackdrop').classList.add('show');
  document.getElementById('delFacModal').classList.add('show');
}
function closeDelFac() {
  document.getElementById('delFacBackdrop').classList.remove('show');
  document.getElementById('delFacModal').classList.remove('show');
}

// ── PRICING ROWS ──
let pricingIdx = 0;
function addPricingRow(data={}) {
  const i = pricingIdx++;
  const rateTypes  = ['daytime','overnight'];
  const guestTypes = ['kids','adults','general'];
  const row = document.createElement('div');
  row.className = 'pricing-input-row';
  row.id = 'pr_'+i;
  row.innerHTML = `
    <select name="pricing[${i}][rate_type]" class="form-control form-control--plain form-control--sm">
      ${rateTypes.map(t=>`<option value="${t}"${data.rate_type===t?' selected':''}>${t[0].toUpperCase()+t.slice(1)}</option>`).join('')}
    </select>
    <select name="pricing[${i}][guest_type]" class="form-control form-control--plain form-control--sm">
      ${guestTypes.map(t=>`<option value="${t}"${data.guest_type===t?' selected':''}>${t[0].toUpperCase()+t.slice(1)}</option>`).join('')}
    </select>
    <input type="number" name="pricing[${i}][base_price]"  class="form-control form-control--plain form-control--sm" placeholder="0.00" step="0.01" min="0" value="${data.base_price||''}"/>
    <input type="number" name="pricing[${i}][exceed_rate]" class="form-control form-control--plain form-control--sm" placeholder="0.00" step="0.01" min="0" value="${data.exceed_rate||''}"/>
    <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('pr_${i}').remove();syncNoPricing();">
      <i class="fa-solid fa-times"></i>
    </button>`;
  document.getElementById('pricingRows').appendChild(row);
  syncNoPricing();
}
function syncNoPricing() {
  document.getElementById('noPricingMsg').style.display =
    document.getElementById('pricingRows').children.length ? 'none' : 'block';
}
function previewFacPhoto(input) {
  const p = document.getElementById('facPhotoPreview');
  if (input.files && input.files[0]) {
    const r = new FileReader();
    r.onload = e => { p.src=e.target.result; p.style.display='block'; };
    r.readAsDataURL(input.files[0]);
  }
}

// ── ADDON MODAL ──
function openAddAddon() {
  document.getElementById('addonAction').value = 'add_addon';
  document.getElementById('addonId').value     = '';
  document.getElementById('addonModalTitle').textContent = 'Add Add-on';
  document.getElementById('addonForm').reset();
  document.getElementById('addonBackdrop').classList.add('show');
  document.getElementById('addonModal').classList.add('show');
}
function openEditAddon(data) {
  document.getElementById('addonAction').value          = 'edit_addon';
  document.getElementById('addonId').value              = data.addon_id;
  document.getElementById('addonModalTitle').textContent = 'Edit Add-on';
  document.getElementById('addonName').value            = data.addon_name  || '';
  document.getElementById('addonDesc').value            = data.addon_description || '';
  document.getElementById('addonPrice').value           = data.addon_price || '';
  document.getElementById('addonLimit').value           = data.limit_per_reservation || '';
  document.getElementById('addonBackdrop').classList.add('show');
  document.getElementById('addonModal').classList.add('show');
}
function closeAddonModal() {
  document.getElementById('addonBackdrop').classList.remove('show');
  document.getElementById('addonModal').classList.remove('show');
}
function confirmDeleteAddon(id, name) {
  document.getElementById('delAddonId').value          = id;
  document.getElementById('delAddonName').textContent  = name;
  document.getElementById('delAddonBackdrop').classList.add('show');
  document.getElementById('delAddonModal').classList.add('show');
}
function closeDelAddon() {
  document.getElementById('delAddonBackdrop').classList.remove('show');
  document.getElementById('delAddonModal').classList.remove('show');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>