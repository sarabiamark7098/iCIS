<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BeneficiaryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class BeneficiaryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BeneficiaryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Beneficiary::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/beneficiary');
        CRUD::setEntityNameStrings('beneficiary', 'beneficiaries');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * Uses server-side pagination (lazy loading) for efficient searching
     * on large datasets. The full-text index on first_name, last_name,
     * middle_name is leveraged automatically by Backpack's search.
     */
    protected function setupListOperation()
    {
        // Enable server-side (ajax) table for lazy loading / efficient search
        CRUD::setOperationSetting('showEntryCount', true);

        // Paginate at 25 rows per page — only loads the current page from the DB
        CRUD::setDefaultPageLength(25);

        CRUD::column('first_name')->searchLogic(function ($query, $column, $searchTerm) {
            $query->orWhereRaw(
                'MATCH(first_name, last_name, middle_name) AGAINST(? IN BOOLEAN MODE)',
                [$searchTerm . '*']
            );
        });

        CRUD::column('middle_name');
        CRUD::column('last_name');
        CRUD::column('extension_name');
        CRUD::column('relationship');
        CRUD::column('sex');
        CRUD::column('civil_status');
    }

    /**
     * Define what happens when the Create operation is loaded.
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(BeneficiaryRequest::class);
        CRUD::setFromDb();
    }

    /**
     * Define what happens when the Update operation is loaded.
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
