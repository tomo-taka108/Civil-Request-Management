<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | バリデーション言語行（日本語）
    |--------------------------------------------------------------------------
    |
    | :attribute は「attributes」で定義した日本語名に置換される。
    | 未定義の項目はカラム名がそのまま入るため、フォームで使う項目は
    | できるだけ attributes に追加すること。
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeは有効なURLではありません。',
    'after' => ':attributeには:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeには:date以降の日付を指定してください。',
    'alpha' => ':attributeはアルファベットのみ使用できます。',
    'alpha_dash' => ':attributeはアルファベット・数字・ダッシュ（-）・下線（_）が使用できます。',
    'alpha_num' => ':attributeはアルファベットと数字が使用できます。',
    'array' => ':attributeは配列でなければなりません。',
    'ascii' => ':attributeは半角の英数字と記号のみ使用できます。',
    'before' => ':attributeには:dateより前の日付を指定してください。',
    'before_or_equal' => ':attributeには:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:min KBから:max KBの間で指定してください。',
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
        'string' => ':attributeは:min文字から:max文字の間で指定してください。',
    ],
    'boolean' => ':attributeはtrueかfalseを指定してください。',
    'confirmed' => ':attributeと確認用の入力が一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは正しい日付ではありません。',
    'date_equals' => ':attributeには:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:format形式で指定してください。',
    'decimal' => ':attributeは:decimal桁の小数で指定してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'digits' => ':attributeは:digits桁で指定してください。',
    'digits_between' => ':attributeは:min桁から:max桁の間で指定してください。',
    'dimensions' => ':attributeの画像サイズが正しくありません。',
    'distinct' => ':attributeに重複した値があります。',
    'doesnt_end_with' => ':attributeは次の値で終わってはいけません: :values',
    'doesnt_start_with' => ':attributeは次の値で始まってはいけません: :values',
    'email' => ':attributeは有効なメールアドレス形式で指定してください。',
    'ends_with' => ':attributeは次のいずれかで終わる必要があります: :values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'file' => ':attributeはファイルを指定してください。',
    'filled' => ':attributeは必須です。',
    'gt' => [
        'array' => ':attributeは:value個より多く指定してください。',
        'file' => ':attributeは:value KBより大きくしてください。',
        'numeric' => ':attributeは:valueより大きくしてください。',
        'string' => ':attributeは:value文字より多く指定してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上指定してください。',
        'file' => ':attributeは:value KB以上にしてください。',
        'numeric' => ':attributeは:value以上にしてください。',
        'string' => ':attributeは:value文字以上で指定してください。',
    ],
    'image' => ':attributeは画像を指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeは:otherに含まれていません。',
    'integer' => ':attributeは整数で指定してください。',
    'ip' => ':attributeは有効なIPアドレスで指定してください。',
    'ipv4' => ':attributeは有効なIPv4アドレスで指定してください。',
    'ipv6' => ':attributeは有効なIPv6アドレスで指定してください。',
    'json' => ':attributeは有効なJSON文字列で指定してください。',
    'lowercase' => ':attributeは小文字で指定してください。',
    'lt' => [
        'array' => ':attributeは:value個より少なく指定してください。',
        'file' => ':attributeは:value KBより小さくしてください。',
        'numeric' => ':attributeは:valueより小さくしてください。',
        'string' => ':attributeは:value文字より少なく指定してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下で指定してください。',
        'file' => ':attributeは:value KB以下にしてください。',
        'numeric' => ':attributeは:value以下にしてください。',
        'string' => ':attributeは:value文字以下で指定してください。',
    ],
    'mac_address' => ':attributeは有効なMACアドレスで指定してください。',
    'max' => [
        'array' => ':attributeは:max個以下で指定してください。',
        'file' => ':attributeは:max KB以下にしてください。',
        'numeric' => ':attributeは:max以下にしてください。',
        'string' => ':attributeは:max文字以下で指定してください。',
    ],
    'max_digits' => ':attributeは:max桁以下で指定してください。',
    'mimes' => ':attributeは次のファイル形式を指定してください: :values',
    'mimetypes' => ':attributeは次のファイル形式を指定してください: :values',
    'min' => [
        'array' => ':attributeは:min個以上指定してください。',
        'file' => ':attributeは:min KB以上にしてください。',
        'numeric' => ':attributeは:min以上にしてください。',
        'string' => ':attributeは:min文字以上で指定してください。',
    ],
    'min_digits' => ':attributeは:min桁以上で指定してください。',
    'missing' => ':attributeは存在してはいけません。',
    'multiple_of' => ':attributeは:valueの倍数で指定してください。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeは数値で指定してください。',
    'password' => [
        'letters' => ':attributeには文字を含めてください。',
        'mixed' => ':attributeには大文字と小文字を含めてください。',
        'numbers' => ':attributeには数字を含めてください。',
        'symbols' => ':attributeには記号を含めてください。',
        'uncompromised' => ':attributeが漏洩したパスワードに含まれています。別のパスワードを指定してください。',
    ],
    'present' => ':attributeが存在していません。',
    'prohibited' => ':attributeは指定できません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは指定できません。',
    'prohibited_unless' => ':otherが:valuesに含まれない場合、:attributeは指定できません。',
    'prohibits' => ':attributeは:otherの指定を禁止しています。',
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeは必須です。',
    'required_array_keys' => ':attributeには次のキーを含めてください: :values',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'required_if_accepted' => ':otherが承認された場合、:attributeは必須です。',
    'required_unless' => ':otherが:valuesに含まれない場合、:attributeは必須です。',
    'required_with' => ':valuesが指定されている場合、:attributeは必須です。',
    'required_with_all' => ':valuesがすべて指定されている場合、:attributeは必須です。',
    'required_without' => ':valuesが指定されていない場合、:attributeは必須です。',
    'required_without_all' => ':valuesがすべて指定されていない場合、:attributeは必須です。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeは:size個で指定してください。',
        'file' => ':attributeは:size KBにしてください。',
        'numeric' => ':attributeは:sizeにしてください。',
        'string' => ':attributeは:size文字で指定してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる必要があります: :values',
    'string' => ':attributeは文字列で指定してください。',
    'timezone' => ':attributeは有効なタイムゾーンで指定してください。',
    'unique' => ':attributeは既に使用されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で指定してください。',
    'url' => ':attributeは有効なURL形式で指定してください。',
    'ulid' => ':attributeは有効なULIDで指定してください。',
    'uuid' => ':attributeは有効なUUIDで指定してください。',

    /*
    |--------------------------------------------------------------------------
    | カスタムバリデーションメッセージ
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 属性名の日本語化
    |--------------------------------------------------------------------------
    |
    | :attribute プレースホルダを分かりやすい日本語名に置換する。
    | 新しいフォーム項目を追加したら、ここにも追記すること。
    |
    */

    'attributes' => [
        // 認証・ユーザー
        'user_id' => 'ユーザーID',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'current_password' => '現在のパスワード',
        'name' => '氏名',
        'office_id' => '所属事務所',
        'department' => '担当部署',
        'role' => '権限区分',
        'status' => 'アカウント状態',
    ],

];
