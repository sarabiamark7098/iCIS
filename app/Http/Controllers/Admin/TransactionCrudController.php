<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\TransactionRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class TransactionCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Transaction::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/transaction');
        CRUD::setEntityNameStrings('transaction', 'transactions');
    }

    protected function setupListOperation()
    {
        CRUD::setOperationSetting('showEntryCount', true);
        CRUD::setDefaultPageLength(25);

        // Only show transactions from active (non-archived, non-deleted) imports
        CRUD::addBaseClause('whereHas', 'import', function ($q) {
            $q->whereNull('archived_at')->whereNull('deleted_at');
        });

        // Profile name columns
        CRUD::addColumn([
            'name' => 'profile.first_name',
            'label' => 'Profile First Name',
            'type' => 'text',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('profile', function ($q) use ($searchTerm) {
                    $q->whereRaw(
                        'MATCH(first_name, last_name, middle_name) AGAINST(? IN BOOLEAN MODE)',
                        [$searchTerm . '*']
                    );
                });
            },
        ]);
        CRUD::addColumn([
            'name' => 'profile.last_name',
            'label' => 'Profile Last Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.middle_name',
            'label' => 'Profile Middle Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.extension_name',
            'label' => 'Profile Ext Name',
            'type' => 'text',
        ]);

        // Beneficiary name columns
        CRUD::addColumn([
            'name' => 'beneficiary.first_name',
            'label' => 'Beneficiary First Name',
            'type' => 'text',
            'searchLogic' => function ($query, $column, $searchTerm) {
                $query->orWhereHas('beneficiary', function ($q) use ($searchTerm) {
                    $q->whereRaw(
                        'MATCH(first_name, last_name, middle_name) AGAINST(? IN BOOLEAN MODE)',
                        [$searchTerm . '*']
                    );
                });
            },
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.last_name',
            'label' => 'Beneficiary Last Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.middle_name',
            'label' => 'Beneficiary Middle Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.extension_name',
            'label' => 'Beneficiary Ext Name',
            'type' => 'text',
        ]);

        // Transaction fields
        CRUD::column('assistance_type');
        CRUD::column('assistance_mode');
        CRUD::column('assistance_amount');
        CRUD::column('status');
        CRUD::column('completed_at');
    }

    protected function setupShowOperation()
    {
        // Profile section
        CRUD::addColumn([
            'name' => 'profile_header',
            'type' => 'custom_html',
            'value' => '<h5 class="mt-3 mb-2"><strong>Profile Information</strong></h5><hr>',
        ]);
        CRUD::addColumn([
            'name' => 'profile.first_name',
            'label' => 'Profile First Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.last_name',
            'label' => 'Profile Last Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.middle_name',
            'label' => 'Profile Middle Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.extension_name',
            'label' => 'Profile Ext Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.birthday',
            'label' => 'Profile Birthday',
            'type' => 'date',
        ]);
        CRUD::addColumn([
            'name' => 'profile.sex',
            'label' => 'Profile Sex',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'profile.civil_status',
            'label' => 'Profile Civil Status',
            'type' => 'text',
        ]);

        // Beneficiary section
        CRUD::addColumn([
            'name' => 'beneficiary_header',
            'type' => 'custom_html',
            'value' => '<h5 class="mt-3 mb-2"><strong>Beneficiary Information</strong></h5><hr>',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.first_name',
            'label' => 'Beneficiary First Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.last_name',
            'label' => 'Beneficiary Last Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.middle_name',
            'label' => 'Beneficiary Middle Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.extension_name',
            'label' => 'Beneficiary Ext Name',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.birthday',
            'label' => 'Beneficiary Birthday',
            'type' => 'date',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.sex',
            'label' => 'Beneficiary Sex',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.civil_status',
            'label' => 'Beneficiary Civil Status',
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'beneficiary.relationship',
            'label' => 'Beneficiary Relationship',
            'type' => 'text',
        ]);

        // Transaction section
        CRUD::addColumn([
            'name' => 'transaction_header',
            'type' => 'custom_html',
            'value' => '<h5 class="mt-3 mb-2"><strong>Transaction Information</strong></h5><hr>',
        ]);
        CRUD::column('assistance_type');
        CRUD::column('assistance_mode');
        CRUD::column('assistance_amount');
        CRUD::column('status');
        CRUD::column('completed_at');
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(TransactionRequest::class);

        CRUD::addField([
            'name' => 'profile_id',
            'label' => 'Profile',
            'type' => 'select',
            'entity' => 'profile',
            'model' => \App\Models\Profile::class,
            'attribute' => 'full_name',
        ]);

        CRUD::addField([
            'name' => 'beneficiary_id',
            'label' => 'Beneficiary',
            'type' => 'select',
            'entity' => 'beneficiary',
            'model' => \App\Models\Beneficiary::class,
            'attribute' => 'full_name',
        ]);

        CRUD::field('assistance_type');
        CRUD::field('assistance_mode');
        CRUD::field('assistance_amount');
        CRUD::field('status');
        CRUD::field('completed_at')->type('date');
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
