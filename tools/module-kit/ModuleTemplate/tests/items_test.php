<?php

declare(strict_types=1);

/**
 * Run with:  php tools/module-kit/module-test.php ModuleTemplate
 *
 * Before any of this runs the harness has copied the live schema into a
 * scratch database, applied this module's migrations, seeded users 1 (tenant)
 * and 2 (a second tenant to test isolation against), signed user 1 in, and
 * called Module::register().
 *
 * The tests worth keeping when you adapt this file are the tenant-isolation
 * ones: they are the failures that leak one customer's data to another, and
 * they are invisible in manual testing because you are usually signed in as
 * only one tenant.
 */

use App\Core\Auth;
use App\Core\Db;
use App\Core\Hooks;
use App\Modules\ModuleTemplate\Models\TemplateItem;

test('saving returns an id and the row can be read back', function () {
    $id = TemplateItem::save(['label' => 'First', 'note' => 'a note']);

    assert_true($id > 0, 'save() should return the new id');
    assert_same('First', TemplateItem::find($id)['label']);
});

test('an empty label is rejected', function () {
    assert_same(true, isset(TemplateItem::validate(['label' => '   '])['label']));
});

test('a label over 255 characters is rejected', function () {
    assert_same(true, isset(TemplateItem::validate(['label' => str_repeat('x', 256)])['label']));
});

test('a valid row passes validation', function () {
    assert_same([], TemplateItem::validate(['label' => 'fine']));
});

test('updating keeps the same id', function () {
    $id = TemplateItem::save(['label' => 'Before']);
    TemplateItem::save(['label' => 'After'], $id);

    assert_same('After', TemplateItem::find($id)['label']);
    assert_same($id, (int) TemplateItem::find($id)['id']);
});

test('delete removes the row', function () {
    $id = TemplateItem::save(['label' => 'Doomed']);
    TemplateItem::delete($id);

    assert_same(null, TemplateItem::find($id));
});

// ---------------------------------------------------------------------------
// Tenant isolation — the tests that matter most
// ---------------------------------------------------------------------------

test('another tenant cannot read this tenant\'s row', function () {
    $id = TemplateItem::save(['label' => 'Tenant 1 secret']);

    $_SESSION['user_id'] = 2;                 // sign in as the other tenant
    try {
        assert_same(null, TemplateItem::find($id), 'find() must not cross tenants');
    } finally {
        $_SESSION['user_id'] = 1;
    }
});

test('another tenant cannot overwrite this tenant\'s row', function () {
    $id = TemplateItem::save(['label' => 'Original']);

    $_SESSION['user_id'] = 2;
    try {
        TemplateItem::save(['label' => 'Hijacked'], $id);   // same id, wrong tenant
    } finally {
        $_SESSION['user_id'] = 1;
    }

    assert_same('Original', TemplateItem::find($id)['label'], 'UPDATE must filter on ruler, not just id');
});

test('another tenant cannot delete this tenant\'s row', function () {
    $id = TemplateItem::save(['label' => 'Keep me']);

    $_SESSION['user_id'] = 2;
    try {
        TemplateItem::delete($id);
    } finally {
        $_SESSION['user_id'] = 1;
    }

    assert_true(TemplateItem::find($id) !== null, 'DELETE must filter on ruler');
});

test('all() returns only this tenant\'s rows', function () {
    Db::conn()->exec('DELETE FROM template_items');

    TemplateItem::save(['label' => 'Mine']);

    $_SESSION['user_id'] = 2;
    try {
        TemplateItem::save(['label' => 'Theirs']);
        assert_same(1, count(TemplateItem::all()));
    } finally {
        $_SESSION['user_id'] = 1;
    }

    $mine = TemplateItem::all();
    assert_same(1, count($mine));
    assert_same('Mine', $mine[0]['label']);
});

test('every row is stamped with the acting tenant', function () {
    $id = TemplateItem::save(['label' => 'Stamped']);

    assert_same(Auth::tenantId(), (int) TemplateItem::find($id)['ruler']);
});

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------

test('the data hook answers under this module\'s own key', function () {
    $data = Hooks::merge('invoice.list.data', ['ids' => [1, 2, 3]]);

    assert_true(array_key_exists('ModuleTemplate', $data), 'namespace your hook result by module code');
});

test('the badge hook renders nothing without data', function () {
    $html = Hooks::render('render.invoice.row.badges', [
        'invoice' => ['id' => 1],
        'data'    => ['ModuleTemplate' => []],
    ]);

    assert_same('', $html);
});

test('rendered HTML carries no hard-coded colour', function () {
    $html = Hooks::render('render.invoice.row.badges', [
        'invoice' => ['id' => 1],
        'data'    => ['ModuleTemplate' => [1 => ['anything' => true]]],
    ]);

    assert_true(!preg_match('/#[0-9a-f]{3,6}\b/i', $html), 'use design tokens, not hex values');
    assert_true(!str_contains($html, 'rgb('), 'use design tokens, not literal colours');
});
