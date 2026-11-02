<?php

require __DIR__ . '/../config/config.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

\App\Core\Security::bootstrap();
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !\App\Core\Csrf::check(\App\Core\Csrf::submitted())) {
    http_response_code(419);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8">'
        . '<title>Страница устарела</title></head><body style="font-family:sans-serif;max-width:520px;margin:80px auto;line-height:1.5">'
        . '<h1>Страница устарела</h1>'
        . '<p>Форма открыта слишком давно или в другой вкладке. Обновите страницу и попробуйте снова — данные не потеряны.</p>'
        . '<p><a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . 'index.php">Вернуться на главную</a></p>'
        . '</body></html>';
    exit;
}

// Токен CSRF добавляется во все POST-формы автоматически.
ob_start(['App\\Core\\Csrf', 'inject']);

use App\Core\Router;

$router = new Router();

$router->add('site/home',     'SiteController', 'home');
$router->add('site/offer',    'SiteController', 'offer');
$router->add('site/privacy',  'SiteController', 'privacy');
$router->add('site/contacts', 'SiteController', 'contacts');
$router->add('site/lead',     'SiteController', 'lead');

$router->add('auth/login',  'AuthController', 'login');
$router->add('auth/logout', 'AuthController', 'logout');
$router->add('auth/forgot', 'AuthController', 'forgot');
$router->add('auth/reset',  'AuthController', 'reset');
$router->add('auth/password', 'AuthController', 'password');
$router->add('auth/register', 'AuthController', 'register');
$router->add('auth/register-student', 'AuthController', 'registerStudent');
$router->add('auth/verify',         'AuthController', 'verify');
$router->add('auth/verify/challenge', 'AuthController', 'verifyChallenge');
$router->add('auth/verify/expire',  'AuthController', 'verifyExpire');

$router->add('employee/dashboard',  'EmployeeController', 'dashboard');
$router->add('employee/status',     'EmployeeController', 'status');
$router->add('employee/heartbeat',  'EmployeeController', 'heartbeat');
$router->add('employee/notifications',      'EmployeeController', 'notifications');
$router->add('employee/notifications/read', 'EmployeeController', 'notificationRead');
$router->add('employee/autologout', 'EmployeeController', 'autoLogout');
$router->add('employee/lunch/start', 'EmployeeController', 'lunchStart');
$router->add('employee/lunch/end',   'EmployeeController', 'lunchEnd');
$router->add('employee/break/start',  'EmployeeController', 'breakStart');
$router->add('employee/break/end',    'EmployeeController', 'breakEnd');
$router->add('employee/workplace/move', 'EmployeeController', 'workplaceMove');
$router->add('employee/workplace/arrive', 'EmployeeController', 'workplaceArrive');
$router->add('employee/workplace/state', 'EmployeeController', 'workplaceState');
$router->add('employee/force-majeure', 'EmployeeController', 'forceMajeureSubmit');
$router->add('employee/screen/frame', 'EmployeeController', 'screenFrame');
$router->add('employee/screen/stop',  'EmployeeController', 'screenStop');

$router->add('admin/dashboard',       'AdminController', 'dashboard');
$router->add('admin/billing',         'AdminController', 'billing');
$router->add('admin/dashboard/data',  'AdminController', 'dashboardData');
$router->add('admin/notifications/read',   'AdminController', 'notificationRead');
$router->add('admin/notifications/cancel', 'AdminController', 'notificationCancel');
$router->add('admin/employees/allow-reentry', 'AdminController', 'allowReentry');
$router->add('admin/monitoring',      'AdminController', 'monitoring');
$router->add('admin/monitoring/status','AdminController', 'monitoringStatus');
$router->add('admin/monitoring/frame','AdminController', 'monitoringFrame');
$router->add('admin/monitoring/view', 'AdminController', 'monitoringView');
$router->add('admin/timesheet',       'AdminController', 'timesheet');
$router->add('admin/timesheet/export','AdminController', 'timesheetExport');
$router->add('admin/employees/create','AdminController', 'employeeCreate');
$router->add('admin/employees/schedule','AdminController', 'employeeSchedule');
$router->add('admin/tables',          'AdminController', 'tables');
$router->add('admin/tables/create',   'AdminController', 'tableCreate');
$router->add('admin/org',             'AdminController', 'org');
$router->add('admin/org/break-schedule', 'AdminController', 'breakScheduleSave');
$router->add('admin/org/workplace',   'AdminController', 'workplaceSave');
$router->add('admin/org/move-time',   'AdminController', 'workplaceMoveTimeSave');
$router->add('admin/org/force-ack',   'AdminController', 'forceAcknowledge');

$router->add('school/dashboard',            'SchoolController', 'dashboard');
$router->add('school/groups',               'SchoolController', 'groups');
$router->add('school/group',                'SchoolController', 'group');
$router->add('school/groups/rotate',        'SchoolController', 'groupRotate');
$router->add('school/groups/teachers',      'SchoolController', 'groupTeachers');
$router->add('school/students/move',        'SchoolController', 'studentMove');
$router->add('school/students/remove',      'SchoolController', 'studentRemove');
$router->add('school/people',               'SchoolController', 'people');
$router->add('school/lessons',              'SchoolController', 'lessons');
$router->add('school/lessons/delete',       'SchoolController', 'lessonDelete');
$router->add('school/lesson',               'SchoolController', 'lesson');
$router->add('school/lesson/export',        'SchoolController', 'lessonExport');

$router->add('teacher/dashboard',      'TeacherController', 'dashboard');
$router->add('teacher/lesson',         'TeacherController', 'lesson');
$router->add('teacher/lessons/start',  'TeacherController', 'lessonStart');
$router->add('teacher/lesson/status',  'TeacherController', 'lessonStatus');
$router->add('teacher/lesson/close',   'TeacherController', 'lessonClose');
$router->add('teacher/lesson/mark',    'TeacherController', 'lessonMark');
$router->add('teacher/lesson/export',  'TeacherController', 'lessonExport');

$router->add('student/dashboard',  'StudentController', 'dashboard');
$router->add('student/checkin',    'StudentController', 'checkin');
$router->add('student/lesson',     'StudentController', 'lesson');
$router->add('student/heartbeat',  'StudentController', 'heartbeat');
$router->add('student/break/start', 'StudentController', 'breakStart');
$router->add('student/break/end',   'StudentController', 'breakEnd');
$router->add('student/attention',   'StudentController', 'attention');

$router->add('super/login',              'SuperController', 'login');
$router->add('super/logout',             'SuperController', 'logout');
$router->add('super/companies',          'SuperController', 'companies');
$router->add('super/companies/create',   'SuperController', 'companyCreate');
$router->add('super/companies/toggle',   'SuperController', 'companyToggle');
$router->add('super/companies/plan',     'SuperController', 'companyPlan');
$router->add('super/leads',              'SuperController', 'leads');

$route = $_GET['route'] ?? 'site/home';
$router->dispatch($route);
