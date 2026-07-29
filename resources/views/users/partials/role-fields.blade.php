{{--
    権限区分と、一般職員のときだけ表示する所属事務所・担当部署。登録・編集で共用。

    パラメータ:
    - $role           … 現在の権限区分（'staff' | 'admin'）
    - $departments    … 担当部署の value=>label 辞書
    - $selectedOffice … 選択中の office_id（null 可）
    - $selectedDept   … 選択中の担当部署（null 可）
    - $offices        … Office コレクション（親ビューから継承）
--}}
<div class="section-heading">所属・権限</div>
<div class="form-grid">
    <label class="required">権限区分</label>
    <div>
        <div class="radio-group">
            <label><input type="radio" name="role" value="staff" @checked($role === 'staff') onchange="toggleRoleFields()">一般職員</label>
            <label><input type="radio" name="role" value="admin" @checked($role === 'admin') onchange="toggleRoleFields()">システム管理者</label>
        </div>
        <div class="note">システム管理者は特定の事務所に所属せず、全事務所のユーザー管理・全案件の編集/削除が可能です（要件定義書1.3参照）。</div>
        @error('role')<div class="error">{{ $message }}</div>@enderror
    </div>

    {{-- 一般職員のみ入力。管理者選択時は JS で非表示にし、サーバ側でも NULL に矯正する。 --}}
    <label class="required staff-only-label">所属事務所</label>
    <div class="staff-only-field">
        <select name="office_id">
            <option value="">選択してください</option>
            @foreach ($offices as $office)
                <option value="{{ $office->id }}" @selected((string) $selectedOffice === (string) $office->id)>{{ $office->name }}</option>
            @endforeach
        </select>
        <div class="note">担当部署に応じて該当案件の編集・削除権限が付与されます（閲覧は自事務所の全案件が可能）。</div>
        @error('office_id')<div class="error">{{ $message }}</div>@enderror
    </div>

    <label class="required staff-only-label">担当部署</label>
    <div class="staff-only-field">
        <div class="radio-group">
            @foreach ($departments as $value => $label)
                <label><input type="radio" name="department" value="{{ $value }}" @checked($selectedDept === $value)>{{ $label }}</label>
            @endforeach
        </div>
        @error('department')<div class="error">{{ $message }}</div>@enderror
    </div>
</div>
<div class="note admin-only-note" style="display:none;">「システム管理者」を選択したため、所属事務所・担当部署の入力は不要です。</div>

<script>
    function toggleRoleFields() {
        const isAdmin = document.querySelector('input[name="role"]:checked').value === 'admin';
        document.querySelectorAll('.staff-only-label, .staff-only-field').forEach(function (el) {
            el.style.display = isAdmin ? 'none' : '';
        });
        document.querySelectorAll('.admin-only-note').forEach(function (el) {
            el.style.display = isAdmin ? 'block' : 'none';
        });
    }
    // 初期表示（old/既存値）を反映。
    toggleRoleFields();
</script>
