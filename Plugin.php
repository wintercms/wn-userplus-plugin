<?php namespace Winter\UserPlus;

use Yaml;
use File;
use System\Classes\PluginBase;
use Winter\User\Models\User as UserModel;
use Winter\Notify\Models\Notification as NotificationModel;
use Winter\User\Controllers\Users as UsersController;
use Winter\Notify\NotifyRules\SaveDatabaseAction;
use Winter\User\Classes\UserEventBase;

/**
 * UserPlus Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = ['Winter.User', 'Winter.Location', 'Winter.Notify'];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'winter.userplus::lang.plugin.name',
            'description' => 'winter.userplus::lang.plugin.description',
            'author'      => 'Alexey Bobkov, Samuel Georges',
            'icon'        => 'icon-user-plus',
            'homepage'    => 'https://github.com/wintercms/wn-userplus-plugin',
            'replaces'    => ['RainLab.UserPlus' => '<= 1.1.0'],
        ];
    }

    public function boot()
    {
        $this->extendUserModel();
        $this->extendUsersController();
        $this->extendSaveDatabaseAction();
        $this->extendUserEventBase();
    }

    public function registerComponents()
    {
        return [
            \Winter\UserPlus\Components\Notifications::class => 'notifications',
        ];
    }

    protected function extendUserModel()
    {
        UserModel::extend(function($model) {
            $model->addFillable([
                'phone',
                'mobile',
                'company',
                'street_addr',
                'city',
                'zip'
            ]);

            $model->implement[] = 'Winter.Location.Behaviors.LocationModel';

            $model->morphMany['notifications'] = [
                NotificationModel::class,
                'name' => 'notifiable',
                'order' => 'created_at desc'
            ];
        });
    }

    protected function extendUsersController()
    {
        UsersController::extendFormFields(function($widget) {
            // Prevent extending of related form instead of the intended User form
            if (!$widget->model instanceof UserModel) {
                return;
            }

            $configFile = plugins_path('winter/userplus/config/profile_fields.yaml');
            $config = Yaml::parse(File::get($configFile));
            $widget->addTabFields($config);
        });
    }

    public function registerNotificationRules()
    {
        return [
            'events' => [],
            'actions' => [],
            'conditions' => [
                \Winter\UserPlus\NotifyRules\UserLocationAttributeCondition::class
            ],
            'presets' => '$/winter/userplus/config/notify_presets.yaml',
        ];
    }

    protected function extendUserEventBase()
    {
        if (!class_exists(UserEventBase::class)) {
            return;
        }

        UserEventBase::extend(function($event) {
            $event->conditions[] = \Winter\UserPlus\NotifyRules\UserLocationAttributeCondition::class;
        });
    }

    protected function extendSaveDatabaseAction()
    {
        if (!class_exists(SaveDatabaseAction::class)) {
            return;
        }

        SaveDatabaseAction::extend(function ($action) {
            $action->addTableDefinition([
                'label' => 'User activity',
                'class' => UserModel::class,
                'param' => 'user'
            ]);
        });
    }
}
