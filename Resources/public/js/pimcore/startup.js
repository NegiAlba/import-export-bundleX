pimcore.registerNS("pimcore.plugin.pluginimportexport");

pimcore.plugin.pluginimportexport = Class.create(pimcore.plugin.admin, {
    getClassName: function () {
        return "pimcore.plugin.pluginimportexport";
    },

    initialize: function () {
        pimcore.plugin.broker.registerPlugin(this);
    },

    pimcoreReady: function (params, broker) {

        var toolbar = pimcore.globalmanager.get("layout_toolbar");

        var action = new Ext.Action({
            id: "pluginimportexport_menu_item",
            text: t("galilee_import_export"),
            iconCls: "galilee_icon_import_export",
            handler: this.showTab
        });

        toolbar.extrasMenu.add(action);
    },

    showTab: function () {
        pluginimportexportPlugin.panel = new Ext.Panel({
            id: "galileeimport_check_panel",
            title: t("galilee_import_export"),
            iconCls: "galilee_icon_import_export",
            border: false,
            layout: "fit",
            closable: true,
            items: [pluginimportexportPlugin.getTabPanel()]
        });

        var tabPanel = Ext.getCmp("pimcore_panel_tabs");
        tabPanel.add(pluginimportexportPlugin.panel);
        tabPanel.setActiveTab(pluginimportexportPlugin.panel);

        pimcore.layout.refresh();
    },


    getTabPanel: function () {

        var items = [];
        items.push(pluginimportexportPlugin.getGridReportEmail());
        items.push(pluginimportexportPlugin.getGridExport());

        pluginimportexportPlugin.tabbar = new Ext.TabPanel({
            tabPosition: "top",
            region: 'center',
            deferredRender: true,
            enableTabScroll: true,
            border: false,
            items: items,
            activeTab: 0
        });

        return pluginimportexportPlugin.tabbar;
    },


    getGridReportEmail: function () {
        pluginimportexportPlugin.store_email = new Ext.data.JsonStore({
            proxy: {
                url: '/admin-galilee-import/get-report-email',
                type: 'ajax',
                reader: {
                    type: 'json',
                    rootProperty: 'emails'
                }
            },
            fields: [
                "email"
            ]
        });
        pluginimportexportPlugin.store_email.load();

        var typeColumns = [
            {
                header: t("galilee_report_recipients"),
                width: 500,
                sortable: false,
                dataIndex: 'email'
            },
            {
                xtype: 'actioncolumn',
                width: 30,
                items: [{
                    tooltip: t('delete'),
                    icon: "/bundles/galileeimportexport/img/delete.svg",
                    handler: function (grid, rowIndex) {
                        Ext.Ajax.request({
                            url: "/admin-galilee-import/delete-report-email",
                            params: {
                                index: rowIndex
                            },
                            success: function (response) {
                                pluginimportexportPlugin.store_email.reload();
                            }.bind(this)
                        });
                    }.bind(this)
                }]
            },
        ];

        this.pagingtoolbar = pimcore.helpers.grid.buildDefaultPagingToolbar(this.store);
        pluginimportexportPlugin.grid_email = new Ext.grid.GridPanel({
            frame: false,
            autoScroll: true,
            store: pluginimportexportPlugin.store_email,
            columns: typeColumns,
            trackMouseOver: true,
            columnLines: true,
            stripeRows: true,
            title: t('galilee_report_emails_recipients'),
            viewConfig: {forceFit: true},
            tbar: {
                items: [
                    {
                        text: t("galilee_add_email_recipients"),
                        iconCls: "pimcore_icon_add",
                        handler: this.addReportEmail.bind(this)
                    }
                ]
            },
            bbar: this.pagingtoolbar
        });

        return pluginimportexportPlugin.grid_email;
    },


    addReportEmail: function () {
        Ext.MessageBox.prompt(t("galilee_add_recipient_email"), "E-mail", this.addReportEmailComplete.bind(this), null, null, "");
    },


    addReportEmailComplete: function (button, value, object) {
        var email = value.replace(/ /g, '');

        if (button == "ok" && validateEmail(email)) {
            Ext.Ajax.request({
                url: "/admin-galilee-import/add-report-email",
                params: {
                    email: email
                },
                success: function (response) {
                    pluginimportexportPlugin.store_email.reload();
                }.bind(this)
            });
        }
        else if (button == "cancel") {
            return false;
        }
        else {
            Ext.Msg.alert('', t("galilee_invalid_email"));
        }
    },




    /**
     *
     * EXPORT
     *
     */

    getGridExport: function() {
        pluginimportexportPlugin.store = new Ext.data.JsonStore({
            proxy: {
                url: '/admin-galilee-export/get-export',
                type: 'ajax',
                reader: {
                    type: 'json',
                    rootProperty: 'export'
                }
            },
            fields: [
                "export"
            ]
        });
        pluginimportexportPlugin.store.load();

        var typeColumns = [
            {
                header: t("galilee_last_export_date"),
                width: 500,
                sortable: true,
                dataIndex: 'export'
            },
        ];

        this.pagingtoolbar = pimcore.helpers.grid.buildDefaultPagingToolbar(this.store);
        pluginimportexportPlugin.grid = new Ext.grid.GridPanel({
            frame:          false,
            autoScroll:     true,
            store:          pluginimportexportPlugin.store,
            columns:        typeColumns,
            title: t('galilee_exports'),
            trackMouseOver: true,
            columnLines:    true,
            stripeRows:     true,
            viewConfig:     { forceFit: true },
            tbar: {
                items: [
                    {
                        text: t("galilee_new_export"),
                        iconCls: "pimcore_icon_add",
                        handler: this.createExport.bind(this)
                    }
                ]
            },
            bbar: this.pagingtoolbar,
        });

        return pluginimportexportPlugin.grid;
    },

    createExport: function () {

        var defaultDate = new Date();

        if (pluginimportexportPlugin.store.totalCount > 0 && pluginimportexportPlugin.store.last().data) {

            defaultDate = pluginimportexportPlugin.store.last().data.export;
            var defaultDateSplit = defaultDate.split("/");
            defaultDate = defaultDateSplit[0]+'-'+defaultDateSplit[1]+'-'+defaultDateSplit[2];

        }

        win = new Ext.Window({
            modal: true,
            title: t("galilee_export_from_date"),
            items: [
                {
                    xtype:'datefield',
                    id: 'export_date',
                    value: defaultDate,
                    format: 'd-m-Y H:i:s',
                    style: 'margin:15px;width:400px;',
                },
                {
                    xtype: 'button',
                    name: 'ok',
                    text: t('galilee_run_export'),
                    style:'margin:15px;',
                    handler: function() {
                        value = Ext.getCmp('export_date').getValue();
                        Ext.Ajax.request({
                            url: "/admin-galilee-export/create-export",
                            params: {
                                export_date: value
                            },
                            success: function (response) {
                                pluginimportexportPlugin.store.reload();
                                win.close();
                            }.bind(this)
                        });
                    }
                }
            ],
        })

        win.show();
    },

});

var pluginimportexportPlugin = new pimcore.plugin.pluginimportexport();

function validateEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}