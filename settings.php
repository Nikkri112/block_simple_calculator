<?php
defined('MOODLE_INTERNAL') || die();

// Проверка на возможность доступа к настройкам
if ($hassiteconfig) {

    // Создаем страницу настроек для плагина
    $settings = new admin_settingpage('simple_calculator', get_string('pluginname', 'block_simple_calculator'));

    // Текстовое поле для настройки
    $settings->add(new admin_setting_configtext(
        'simple_calculator/id_setting',   // Имя настройки
        get_string('idSettingName', 'block_simple_calculator'), // Название настройки (отображается на странице)
        get_string('idSettingDesc', 'block_simple_calculator'), // Описание настройки
        '',  // Значение по умолчанию
        PARAM_TEXT // Тип данных
    ));
    $settings->add(new admin_setting_configtext(
        'simple_calculator/names_setting',   // Имя настройки
        get_string('namesSettingName', 'block_simple_calculator'), // Название настройки (отображается на странице)
        get_string('namesSettingDesc', 'block_simple_calculator'), // Описание настройки
        '',  // Значение по умолчанию
        PARAM_TEXT // Тип данных
    ));
    $settings->add(new admin_setting_configtext(
        'simple_calculator/courseId_setting',   // Имя настройки
        get_string('courseIDSettingName', 'block_simple_calculator'), // Название настройки (отображается на странице)
        get_string('courseIDSettingDesc', 'block_simple_calculator'), // Описание настройки
        '',  // Значение по умолчанию
        PARAM_TEXT // Тип данных
    ));
    $settings->add(new admin_setting_configtext(
        'simple_calculator/uniqueString_setting',   // Имя настройки
        get_string('uniqueStringSettingName', 'block_simple_calculator'), // Название настройки (отображается на странице)
        get_string('uniqueStringSettingDesc', 'block_simple_calculator'), // Описание настройки
        '',  // Значение по умолчанию
        PARAM_TEXT // Тип данных
    ));
    $settings->add(new admin_setting_configtext(
        'simple_calculator/altCourseId_setting',   // Имя настройки
        get_string('altCourseIdSettingName', 'block_simple_calculator'), // Название настройки (отображается на странице)
        get_string('altCourseIdSettingDesc', 'block_simple_calculator'), // Описание настройки
        '',  // Значение по умолчанию
        PARAM_TEXT // Тип данных
    ));
    $ADMIN->add('localplugins', $settings);
}