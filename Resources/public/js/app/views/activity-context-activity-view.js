define([
    'jquery',
    'underscore',
    'orotranslation/js/translator',
    'routing',
    'oroui/js/messenger',
    'oroui/js/app/views/base/view',
    'oroui/js/mediator',
    'oroactivity/js/app/models/activity-context-activity-collection'
], function($, _, __, routing, messenger, BaseView, mediator, ActivityContextActivityCollection) {
    'use strict';

    var ActivityContextActivityView;

    /**
     * @export oroactivity/js/app/views/activity-context-activity-view
     */
    ActivityContextActivityView = BaseView.extend({
        options: {},
        events: {},

        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);

            this.template = _.template($('#activity-context-activity-list').html());
            this.$container = options.$container;
            this.$containerContextTargets = $(options.$container.context).find('.activity-context-activity-items');
            this.collection = new ActivityContextActivityCollection('oro_api_delete_activity_relation');
            this.initEvents();

            if (this.options.contextTargets) {
                this.collection.reset();
                for (var i in this.options.contextTargets) {
                    if (this.options.contextTargets.hasOwnProperty(i)) {
                        this.collection.add(this.options.contextTargets[i]);
                    }
                }
            }

            /**
            * on adding activity item listen to "widget:doRefresh:activity-context-activity-list-widget"
            */
            this.listenTo(mediator, 'widget:doRefresh:activity-context-activity-list-widget', this.doRefresh, this);
            this.listenTo(mediator, 'widget:doRefresh:activity-thread-context', this.doRefresh, this);
            ActivityContextActivityView.__super__.initialize.apply(this, arguments);
            this.render();
        },

        add: function(model) {
            this.collection.add(model);
        },

        doRefresh: function() {
            var url = routing.generate('oro_api_get_activity_context', {
                activity: this.options.activityClass,
                id: this.options.entityId
            });
            var collection = this.collection;
            $.ajax({
                method: 'GET',
                url: url,
                success: function(r) {
                    collection.reset();
                    collection.add(r);
                }
            });
        },

        render: function() {
            if (this.collection.length === 0) {
                this.$el.hide();
            } else {
                this.$el.show();
            }
        },

        initEvents: function() {
            var self = this;

            this.collection.on('reset', function() {
                self.$containerContextTargets.html('');
            });

            this.collection.on('add', function(model) {
                var view = self.template({
                    entity: model,
                    inputName: self.inputName
                });

                var $view = $(view);
                self.$containerContextTargets.append($view);

                $view.find('i.icon-remove').click(function() {
                    model.destroy({
                        success: function(model, response) {
                            messenger.notificationFlashMessage('success', __('oro.activity.contexts.removed'));

                            if (self.options.target &&
                                model.get('targetClassName') === self.options.target.className &&
                                model.get('targetId') === self.options.target.id) {
                                mediator.trigger('widget_success:activity_list:item:update');
                            } else {
                                mediator.trigger('widget:doRefresh:activity-context-activity-list-widget');
                            }
                        },
                        error: function(model, response) {
                            messenger.showErrorMessage(__('oro.ui.item_delete_error'), response.responseJSON || {});
                        }
                    });
                });
            });
        }
    });

    return ActivityContextActivityView;
});
