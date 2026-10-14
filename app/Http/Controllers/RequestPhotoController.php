<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceptionPhotoRequest;
use App\Models\Request;
use App\Models\RequestPhoto;
use App\Models\User;
use App\Support\RequestPhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

/**
 * 現場写真。要件定義書 §2.8。
 *
 * 対応履歴に紐づく写真は RequestLogController（履歴の追加・編集）で登録し、
 * ここでは受付時の写真の登録と、写真の削除（受付時・対応履歴分とも）を扱う。
 *
 * 権限はルートの can:managePhoto で担保済み。{photo} は scopeBindings() により
 * 親案件の写真に限って解決される（別案件のURLからは404。routes/web.php 参照）。
 */
class RequestPhotoController extends Controller
{
    public function store(StoreReceptionPhotoRequest $form, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $form->user();

        /** @var array<int, UploadedFile> $files */
        $files = $form->file('photos', []);
        RequestPhotoStore::store($request, null, $files, $user);

        return redirect()->route('requests.show', $request)
            ->with('status', '受付時の写真を登録しました。');
    }

    public function destroy(Request $request, RequestPhoto $photo): RedirectResponse
    {
        RequestPhotoStore::delete($photo);

        return redirect()->route('requests.show', $request)
            ->with('status', '写真を削除しました。');
    }
}
