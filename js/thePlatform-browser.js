/* thePlatform Video Manager Wordpress Plugin
 Copyright (C) 2013-2014  thePlatform for Media Inc.
 
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */


(function($) {
    /**
     * UI Methods
     * @type {Object}
     */
    var UI = {
        /**
         * Refresh the infinite scrolling media list based on the selected category and search options
         * @return {void}
         */
        refreshView: function() {
            if (viewLoading) {
                return;
            }
            viewLoading = true;
            UI.notifyUser('clear'); //clear alert box.
            UI.updateContentPane({
                title: ''
            });
            var $mediaList = $('#media-list');
            //TODO: If sorting clear search?
            var queryObject = {
                search: $('#input-search').val(),
                category: tpHelper.selectedCategory,
                sort: Search.getSort(),
                desc: $('#sort-desc').data('sort'),
                myContent: $('#my-content-cb').prop('checked')
            };

            tpHelper.queryParams = queryObject;
            var newFeed = API.buildMediaQuery(queryObject);
            
            tpHelper.queryString = API.buildMediaQuery(queryObject);
            tpHelper.feedEndRange = 0;
            $mediaList.empty();
            $mediaList.infiniteScroll('reset');
        },

        buildCategoryAccordion: function(resp) {
            var entries = resp['entries'];
            var categorySource = $("#category-template").html();
            var categoryTemplate = _.template(categorySource);

            // Set an event handler for All Videos
            jQuery('.category').first().click(Events.onClickCategory);

            // Add each category
            for (var idx in entries) {
                var entryTitle = entries[idx]['title'];
                category = categoryTemplate({
                    entryTitle: entryTitle
                });
                newCategory = $(category);
                newCategory.on('click', Events.onClickCategory);
                $('#list-categories').append(newCategory);
            }

            //Add an empty row for scrolling
            $('#list-categories').append('<div style="height: 70px;"></div>');
        },

        notifyUser: function(type, msg) {
            var $msgPanel = $('#message-panel');
            $msgPanel.attr('class', '');
            if (type === 'clear') {
                $msgPanel.attr('class', '');
                msg = '';
            } else {
                $msgPanel.addClass('alert alert-' + type);
                $msgPanel.alert();
            }
            $msgPanel.text(msg);
        },

        updatePublishProfiles: function(mediaId) {
            API.getProfileResults(mediaId, function(data) {
                var revokeDropdown = jQuery('#publish_status');
                revokeDropdown.empty();
                var publishDropdown = jQuery('#edit_publishing_profile');

                for (var i = 0; i < data.length; i++) {
                    if (data[i].status == 'Processed') {
                        var option = document.createElement('option');
                        option.value = data[i].profileId;
                        option.text = publishDropdown.find('option[value="' + data[i].profileId + '"]').text();
                        revokeDropdown.append(option);
                    }
                };

                if (revokeDropdown.children().length == 0) {
                    revokeDropdown.attr('disabled', 'true');
                } else {
                    revokeDropdown.removeAttr('disabled');
                }
            })
        },

        updateContentPane: function(mediaItem) {
            if (mediaItem.title == '') {
                $('#info-container').css('visibility', 'hidden');
                $('.tpPlayer').css('visibility', 'hidden');
            } else {
                $('#info-container').css('visibility', 'visible');
            }

            var i, catArray, catList;

            var $fields = $('.field')

            $fields.each(function(index, value) {
                var $field = $(value);
                var prefix = $field.data('prefix');
                var dataType = $field.data('type');
                var dataStructure = $field.data('structure');

                var name = $field.data('name');
                var fullName = name
                if (prefix !== undefined)
                    fullName = prefix + '$' + name;
                var value = mediaItem[fullName];

                if (_.isEmpty(value)) {
                    return true; // Empty value, continue the loop                
                }

                // Update the right content pane
                if (name === 'categories') {
                    var catArray = mediaItem.categories || [];
                    var catList = '';
                    for (i = 0; i < catArray.length; i++) {
                        if (catList.length > 0)
                            catList += ', ';
                        catList += catArray[i].name;
                    }
                    value = catList
                } else {
                    if (dataStructure == 'List' || dataStructure == 'Map') {
                        var valString = '';
                        // Lists
                        if (dataStructure == 'List') {
                            for (var i = 0; i < value.length; i++) {
                                valString += Formatting.formatValue(value[i], dataType) + ', ';
                            }
                        }
                        // Maps
                        else {
                            for (var propName in value) {
                                if (value.hasOwnProperty(propName))
                                    valString += propName + ': ' + Formatting.formatValue(value[propName], dataType) + ', ';
                            }
                        }
                        // Remove the last comma
                        if (valString.length)
                            value = valString.substr(0, valString.length - 2);
                        else
                            value = '';
                    } else {
                        value = Formatting.formatValue(value, dataType);
                    }
                }

                $('#media-' + name).html(value || '');


                // Update content on the hidden Edit dialog
                var upload_field = $('#theplatform_upload_' + fullName.replace('$', "\\$"))
                if (!upload_field.hasClass('userid')) {
                    $('#theplatform_upload_' + fullName.replace('$', "\\$")).val(value || '');
                }
            });
        },

        addMediaObject: function(media) {
            //Prevent adding the same media twice.
            // This cannot be filtered out earlier because it only really occurs when
            // Something just gets added.
            if (document.getElementById(media.guid) != null) //Can't use $ because of poor guid format convention.
                return;

            var placeHolder = "";
            if (media.defaultThumbnailUrl === "")
                placeHolder = "holder.js/128x72/text:No Thumbnail";

            var mediaSource = $("#media-template").html();
            var mediaTemplate = _.template(mediaSource);

            var newMedia = mediaTemplate({
                guid: media.guid,
                pid: media.pid,
                placeHolder: placeHolder,
                defaultThumbnailUrl: media.defaultThumbnailUrl,
                title: media.title,
                description: media.description
            });

            newMedia = $(newMedia);
            newMedia.data('guid', media.guid);
            newMedia.data('pid', media.pid);
            newMedia.data('media', media);
            newMedia.data('id', media.id);
            var previewUrl = API.extractVideoUrlfromMedia(media);
            if (previewUrl.length == 0 && tpHelper.isEmbed == "1")
                return;

            newMedia.data('release', previewUrl.pop());

            $('#media-list').append(newMedia);

            newMedia.on('click', Events.onClickMedia);

            //Select the first one on the page.
            if ($('#media-list').children().length < 2)
                $('.media', '#media-list').click();
        }
    }

    /**
     * Search bar functionality
     * @type {Object}
     */
    var Search = {
        getSort: function() {
            var sortMethod = $('option:selected', '#selectpick-sort').val();

            switch (sortMethod) {
                case "Added":
                    sortMethod = "added|desc";
                    break;
                case "Updated":
                    sortMethod = "updated|desc";
                    break;
                case "Title":
                    sortMethod = "title";
                    break;
            }

            return sortMethod || "added";
        },

        getSearch: function() {
            return $('#input-search').val();
        }
    }

    /**
     * Event Handlers
     * @type {Object}
     */
    var Events = {
        onClickMedia: function(e) {
            UI.updateContentPane($(this).data('media'));
            $('.media.selected').removeClass('selected');
            $(this).addClass('selected');

            if (tpHelper.mediaEmbedType == 'pid') {
                tpHelper.currentRelease = 'media/' + $(this).data('pid');
            } else if (tpHelper.mediaEmbedType == 'guid') {
                var accountId = tpHelper.account.substring(tpHelper.account.lastIndexOf('/') + 1);
                tpHelper.currentRelease = 'media/guid/' + accountId + '/' + encodeURIComponent($(this).data('guid'));
            } else {
                tpHelper.currentRelease = $(this).data('release');
            }

            tpHelper.mediaId = $(this).data('id');
            UI.updatePublishProfiles(tpHelper.mediaId);
            tpHelper.selectedThumb = $(this).data('media')['defaultThumbnailUrl'];
            $pdk.controller.resetPlayer();
            if (tpHelper.currentRelease !== undefined) {
                $('#modal-player-placeholder').hide();
                $('.tpPlayer').css('visibility', 'visible');
                $pdk.controller.loadReleaseURL("//link.theplatform.com/s/" + tpHelper.accountPid + "/" + tpHelper.currentRelease, true);
            } else {
                $('.tpPlayer').css('visibility', 'hidden');
                $('#modal-player-placeholder').show();
            }
        },
        onClickCategory: function(e) {
            tpHelper.selectedCategory = $(this).text();
            if (tpHelper.selectedCategory == "All Videos")
                tpHelper.selectedCategory = '';
            $('.category.selected').removeClass('selected');
            $(this).addClass('selected');
            $('#input-search').val(''); //Clear the current search value when we choose a category        

            UI.refreshView();
        },
        onEmbed: function() {
            var player = $('#selectpick-player').val();

            var shortcodeSource = $("#shortcode-template").html();
            var shortcodeTemplate = _.template(shortcodeSource);

            //'[theplatform account="' + tpHelper.accountPid + '" media="' + tpHelper.currentRelease + '" player="' + player + '"]';
            var shortcode = shortcodeTemplate({
                account: tpHelper.accountPid,
                release: tpHelper.currentRelease,
                player: player
            }); 

            var win = window.dialogArguments || opener || parent || top;
            var editor = win.tinyMCE.activeEditor;
            var isVisual = (typeof win.tinyMCE != "undefined") && editor && !editor.isHidden();
            if (isVisual) {
                editor.execCommand('mceInsertContent', false, shortcode.trim());
            } else {
                var currentContent = $('#content', window.parent.document).val();
                if (typeof currentContent == 'undefined')
                    currentContent = '';
                $('#content', window.parent.document).val(currentContent + shortcode.trim());
            }
        },
        onEmbedAndClose: function() {
            this.onEmbed();
            var win = opener || parent
            if (win.jQuery('#tp-embed-dialog').length != 0) {
                win.jQuery('#tp-embed-dialog').dialog('close');
            }
            if (win.tinyMCE.activeEditor != null) {
                win.tinyMCE.activeEditor.windowManager.close();
            }
        },
        onSetImage: function() {
            var post_id = window.parent.$('#post_ID').val();
            if (!tpHelper.selectedThumb || !post_id)
                return;
            var data = {
                action: 'set_thumbnail',
                img: tpHelper.selectedThumb,
                id: post_id,
                _wpnonce: tp_browser_local.tp_nonce['set_thumbnail']
            };

            $.post(tp_edit_upload_local.ajaxurl, data, function(response) {
                if (response.success)
                    window.parent.$('#postimagediv .inside').html(response.data);
            });
        },
        onEditMetadata: function() {
            $("#tp-edit-dialog").dialog({
                modal: true,
                title: 'Edit Media',
                resizable: true,
                minWidth: 800,
                width: 1024,
                open: function() {
                    $('.ui-dialog-titlebar-close').addClass('ui-button');
                },
                close: function() {
                    UI.refreshView();
                }
            }).css("overflow", "hidden");
            return false;
        },
        onMediaListBottom: function(callback) {
            var MAX_RESULTS = 20;
            $('#load-overlay').show(); // show loading before we call getVideos
            var theRange = parseInt(tpHelper.feedEndRange);
            theRange = (theRange + 1) + '-' + (theRange + MAX_RESULTS);
            API.getVideos(theRange, function(resp) {
                if (resp['isException']) {
                    $('#load-overlay').hide();
                    //what do we do on error?
                }

                tpHelper.feedResultCount = resp['entryCount'];
                tpHelper.feedStartRange = resp['startIndex'];
                tpHelper.feedEndRange = 0;
                if (resp['entryCount'] > 0)
                    tpHelper.feedEndRange = resp['startIndex'] + resp['entryCount'] - 1;
                else
                    UI.notifyUser('info', 'No Results');

                var entries = resp['entries'];
                for (var i = 0; i < entries.length; i++)
                    UI.addMediaObject(entries[i]);

                $('#load-overlay').hide();
                Holder.run();
                callback(parseInt(tpHelper.feedResultCount) == MAX_RESULTS); //True if there are still more results.
            });
        },
        onMouseScroll: function(ev) {
            var $this = $(this),
                scrollTop = this.scrollTop,
                scrollHeight = this.scrollHeight,
                height = $this.height(),
                delta = (ev.type == 'DOMMouseScroll' ? ev.originalEvent.detail * -40 : ev.originalEvent.wheelDelta),
                up = delta > 0;

            var prevent = function() {
                ev.stopPropagation();
                ev.preventDefault();
                ev.returnValue = false;
                return false;
            };

            if (!up && -delta > scrollHeight - height - scrollTop) {
                // Scrolling down, but this will take us past the bottom.
                $this.scrollTop(scrollHeight);
                return prevent();
            } else if (up && delta > scrollTop) {
                // Scrolling up, but this will take us past the top.
                $this.scrollTop(0);
                return prevent();
            }
        }
    }

    /**
     * Data Formatting Methods
     * @type {Object}
     */
    var Formatting = {
        formatValue: function(value, dataType) {
            switch (dataType) {
                case 'DateTime':
                    value = new Date(value);
                    break;
                case 'Duration':
                    value = Formatting.secondsToDuration(value);
                    break;
                case 'Link':
                    value = '<a href="' + value.href + '" target="_blank">' + value.title + '</a>';
                    break;
            }
            return value;
        },

        secondsToDuration: function(secs) {
            var t = new Date(1970, 0, 1);
            t.setSeconds(secs);
            var s = t.toTimeString().substr(0, 8);
            if (secs > 86399)
                s = Math.floor((t - Date.parse("1/1/70")) / 3600000) + s.substr(2);
            return s;
        }
    }

    /**
     * mpx API calls
     * @type {Object}
     */
    var API = {
        getVideos: function(range, callback) {

            var data = {
                _wpnonce: tp_browser_local.tp_nonce['get_videos'],
                action: 'get_videos',
                range: range,
                query: tpHelper.queryString,
                isEmbed: tpHelper.isEmbed,
                myContent: jQuery('#my-content-cb').prop('checked')
            };

            jQuery.post(tp_browser_local.ajaxurl, data, function(resp) {
                viewLoading = false;
                resp = JSON.parse(resp);
                if (resp.isException) {
                    UI.notifyUser('danger', resp.description);
                } else {
                    callback(resp);
                }
            });
        },
        buildMediaQuery: function(data) {

            var queryParams = '';
            if (data.category)
                queryParams = queryParams.appendParams({
                    byCategories: data.category
                });

            if (data.search) {
                queryParams = queryParams.appendParams({
                    q: encodeURIComponent(data.search)
                });
                data.sort = ''; //Workaround because solr hates sorts.
            }

            if (data.sort) {
                var sortValue = data.sort + (data.desc ? '|desc' : '');
                queryParams = queryParams.appendParams({
                    sort: sortValue
                });
            }            

            return queryParams;
        },
        getCategoryList: function(callback) {
            var data = {
                _wpnonce: tp_browser_local.tp_nonce['get_categories'],
                action: 'get_categories',
                sort: 'order',
                fields: 'title'
            };

            jQuery.post(tp_browser_local.ajaxurl, data,
                function(resp) {
                    callback(JSON.parse(resp));
                });
        },
       
        //Get a list of release URls
        extractVideoUrlfromMedia: function(media) {
            var res = [];

            if (media.entries)
                media = media['entries'].shift(); //We always only grab the first media in the list THIS SHOULD BE THE ONLY MEDIA.

            if (media && media.content)
                media = media.content;
            else
                return res;

            for (var contentIdx in media) {
                var content = media[contentIdx];
                if ((content.contentType == "video" || content.contentType == "audio") && content.releases) {
                    for (var releaseIndex in content.releases) {
                        if (content.releases[releaseIndex].delivery == "streaming")
                            res.push(content.releases[releaseIndex].pid);
                    }
                }

            }

            return res;
        },
        getProfileResults: function(mediaId, callback) {
            var data = {
                _wpnonce: tp_browser_local.tp_nonce['get_profile_results'],
                action: 'get_profile_results',
                mediaId: mediaId
            };

            jQuery.post(tp_browser_local.ajaxurl, data, function(resp) {
                if (resp.success) {
                    callback(resp.data);
                } else {
                    console.log(resp);
                }

            });
        }
    };

    //Make my life easier by prototyping this into the string.
    String.prototype.appendParams = function(params) {
        var updatedString = this;
        for (var key in params) {
            if (updatedString.indexOf(key + '=') > -1)
                continue;

            // if (updatedString.indexOf('?') > -1)
            updatedString += '&' + key + '=' + params[key];
            // else
            //     updatedString += '?'+key+'='+params[key];
        }
        return updatedString;
    };

    // Set up our template helper method
    _.template.formatDescription = function(description) {
        if (description && description.length > 300) {
            return description.substring(0, 297) + '...';
        }
        return description;
    };

    $(document).ready(function() {
        $pdk.initialize();
        $('#load-overlay').hide();

        $('#btn-embed').click(Events.onEmbed);
        $('#btn-embed-close').click(Events.onEmbedAndClose);
        $('#btn-set-image').click(Events.onSetImage);
        $('#btn-edit').click(Events.onEditMetadata);

        // Only allow scrolling on the current column were on
        $('.scrollable').on('DOMMouseScroll mousewheel', Events.onMouseScroll);

        /**
         * Search form event handlers
         */
        $('#btn-feed-preview').click(UI.refreshView);
        $('input:checkbox', '#my-content').click(UI.refreshView);
        $('#selectpick-sort').on('change', UI.refreshView);
        $('#input-search').keyup(function(event) {
            if (event.keyCode == 13)
                UI.refreshView();
        });

        // Load Categories from mpx
        API.getCategoryList(UI.buildCategoryAccordion);

        /**
         * Set up the infinite scrolling media list and load the first sets of media
         */
        $('#media-list').infiniteScroll({
            threshold: 100,
            onEnd: function() {
                //No more results
                UI.notifyUser('info', 'No more videos available');
            },
            onBottom: Events.onMediaListBottom
        });
    });

})(jQuery);
