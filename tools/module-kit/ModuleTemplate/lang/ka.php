<?php

declare(strict_types=1);

/**
 * This module's own strings. They live here, not in app/lang/*.php — that is
 * the difference that makes a module removable: delete the folder and its
 * text goes with it.
 *
 * Prefix every key ('tpl.') so it can never collide with core's. On a
 * collision core wins, silently.
 */
return [
    'tpl.module_name'        => 'შაბლონი',
    'tpl.module_description' => 'მოდულის საწყისი შაბლონი — დააკოპირეთ და გადაარქვით სახელი.',

    'tpl.nav'        => 'შაბლონი',
    'tpl.nav_list'   => 'სია',
    'tpl.page_title' => 'შაბლონის ჩანაწერები',

    'tpl.label'  => 'დასახელება',
    'tpl.note'   => 'შენიშვნა',
    'tpl.add'    => 'დამატება',
    'tpl.save'   => 'შენახვა',
    'tpl.delete' => 'წაშლა',
    'tpl.saved'  => 'ჩანაწერი შენახულია.',
    'tpl.empty'  => 'ჩანაწერი ჯერ არ არის.',
    'tpl.badge'  => 'შაბლონი',

    'tpl.err_label_required' => 'დასახელება სავალდებულოა.',
    'tpl.err_label_long'     => 'დასახელება ძალიან გრძელია (მაქსიმუმი 255 სიმბოლო).',
];
