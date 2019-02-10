
(function() {

    tinymce.create('tinymce.plugins.pboerse', {
        /**
         * Initializes the plugin, this will be executed after the plugin has been created.
         * This call is done before the editor instance has finished it's initialization so use the onInit event
         * of the editor instance to intercept that event.
         *
         * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
         * @param {string} url Absolute URL to where the plugin is located.
         */
        init : function(ed, url) {
            var state;

            ed.addButton('pb_button1', {
                text : 'PB',
                title : 'Auf Projektbörse veröffentlichen?',
                cmd : 'pb_button1',
                //stateSelector: '.class-of-node',
                //image : url + '/pboerse.svg',

                onclick: function () {


                },

                onpostrender: function() {
                    var btn = this;
                    ed.on('pb_button1', function(e) {
                        btn.active(e.state);
                    });
                }
            });

            ed.addCommand('pb_button1', function() {

                state = !state; /* Switching state */
                ed.fire('pb_button1', {state: state});

                if (state){
                    /* Button active */
                    document.getElementById("i-am-hidden").value = "1";



                }
                else {
                    /* Button inactive */
                    document.getElementById("i-am-hidden").value = "0";
                }

            });

        },

        /**
         * Creates control instances based in the incomming name. This method is normally not
         * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
         * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
         * method can be used to create those.
         *
         * @param {String} n Name of the control to create.
         * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
         * @return {tinymce.ui.Control} New control instance or null if no control was created.
         */
        createControl : function(n, cm) {
            return null;
        },

        /**
         * Returns information about the plugin as a name/value array.
         * The current keys are longname, author, authorurl, infourl and version.
         *
         * @return {Object} Name/value array containing information about the plugin.
         */
        getInfo : function() {
            return {
                longname : 'PB Buttons',
                author : 'A. Paulick',
                authorurl : 'https://github.com/andreaspaulick',
                infourl : '',
                version : "0.1"
            };
        }
    });

    // Register plugin
    tinymce.PluginManager.add( 'pboerse', tinymce.plugins.pboerse );
})();