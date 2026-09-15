<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Account;

/**
 * AccountController
 *
 * Standard resource controller — the template to copy for any new
 * simple CRUD module (index/create/store/show/edit/update/destroy).
 */
class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->query('type');
        $page = max(1, (int) $request->query('page', 1));
        $query = Account::query()->orderBy('code');
        if ($type) {
            $query->where('type', '=', $type);
        }
        $paginated = $query->paginate($page, 15);

        foreach ($paginated['data'] as &$account) {
            $account['balance'] = Account::balance((int) $account['id']);
        }
        unset($account);

        return $this->view('accounts.index', [
            'accounts'   => $paginated['data'],
            'pagination' => $paginated,
            'types'      => Account::TYPES,
            'activeType' => $type,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('accounts.form', ['account' => null, 'types' => Account::TYPES]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|max:20',
            'name' => 'required|max:255',
            'type' => 'required|in:' . implode(',', Account::TYPES),
        ]);
        $data['is_active'] = 1;

        $account = Account::create($data);

        return $this->redirect('/accounts/' . $account['id']);
    }

    public function show(Request $request): Response
    {
        $account = Account::findOrFail((int) $request->param('id'));
        $account['balance'] = Account::balance((int) $account['id']);

        return $this->view('accounts.show', ['account' => $account]);
    }

    public function edit(Request $request): Response
    {
        $account = Account::findOrFail((int) $request->param('id'));

        return $this->view('accounts.form', ['account' => $account, 'types' => Account::TYPES]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'code' => 'required|max:20',
            'name' => 'required|max:255',
            'type' => 'required|in:' . implode(',', Account::TYPES),
        ]);

        Account::update($id, $data);

        return $this->redirect('/accounts/' . $id);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        // Soft-disable rather than hard delete: financial history must stay intact.
        Account::update($id, ['is_active' => 0]);

        return $this->redirect('/accounts');
    }
}
