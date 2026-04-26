'use strict';

// MainScreen as an option for delegate functions
class SPAATGScreen extends SPAATGScreenItemBase //= function (MainScreen, processor)
{
	isCustom = true;
	isMedia = true;
	type = 'media';
	altInputNames = [
		'attachment_alt',  //edit-media 
		'attachment-details-alt-text', // media library upload screen / image select
		'attachment-details-two-column-alt-text',
	
	 ];
	 ai_enabled = true; 
	 gutenCheck = []; 


	Init() {
		super.Init();
		
		let settings = spaatg_mediascreen_settings;
		this.settings = settings;

		this.ListenGallery();
		this.ListenGutenberg();


		if (typeof settings.hide_ai !== 'undefined')
		{
			this.ai_enabled = ! settings.hide_ai;
		}
	   
		// This init only in edit-media and pass the ID for safety. 
		if (document.getElementById('attachment_alt') !== null)
		{
			var postInput = document.getElementById('post_ID');
			let item_id = postInput.value; 
			this.FetchAltView(undefined, item_id);
		}
	}

	FetchAltView(aiData, item_id)
	{
		if (false == this.ai_enabled)
		{
			 return;
		}
		var attachmentAlt = this.GetPageAttachmentAlt();

		if (null === attachmentAlt) // No attach alt around
		{
			return; 
		}
		if (typeof item_id === 'undefined')
		{
			console.error('Item_id not passed');
			return; 
		}

		var wp_screen_id = this.settings.wp_screen_id;


		if (typeof aiData !== 'undefined')
		{
			var newAltText = aiData.alt; 
			var newCaption = aiData.caption;
			var newDescription = aiData.description;
			var newTitle = aiData.post_title;
		}

		if (typeof newAltText !== 'undefined' || newAltText < 0)
		{
			var inputs = this.altInputNames;
	
			for (var i = 0; i < inputs.length; i++)
			{
				   var altInput = document.getElementById(inputs[i]); 
				   if (altInput !== null)
				   {
					   if (altInput.dataset.shortpixelAlt != item_id)
					   {
						 console.log('Returned alt, but not ours.', item_id, altInput);
						 continue; 
					   }
					   if (typeof altInput.value !== 'undefined')
					   {
						   altInput.value = newAltText; 	
					   }
					   else
					   {
						   altInput.innerText = newAltText; 	
					   }
					   
				   }
					   
			}
		}
		// edit media screen
		 let captionFields = ['attachment_caption', 'attachment-details-caption', 'attachment-details-two-column-caption']; 
		 let descriptionFields = ['attachment_content', 'attachment-details-description', 'attachment-details-two-column-description']; 
		
		 let postTitleFields = ['attachment-details-title', 'attachment-details-two-column-title'];
		 // This check: the edit-post screen also has a name title field, but this is for the post, not attachment. only replace in attachment screen
		 if ('attachment' == wp_screen_id)
	 	 {	
			postTitleFields.push('title');
		 }
		 
		 if (typeof newCaption !== 'undefined' || newCaption < 0)
		 {
			for (var i = 0; i < captionFields.length; i++)
			{
				let captionField = document.getElementById(captionFields[i]); 
				if (null !== captionField)
				{
					captionField.value = newCaption; 
				}				 
			}
		 }

		 if (typeof newDescription !== 'undefined' || newDescription < 0)
		 {
			for (var i = 0; i < descriptionFields.length; i++)
			{
				let descriptionField = document.getElementById(descriptionFields[i]);
				if (null !== descriptionField)
				{
					 descriptionField.value = newDescription; 
				}
			}
		 }

		 if (typeof newTitle !== 'undefined' || newTitle < 0)
		 {
			for (var i = 0; i < postTitleFields.length; i++)
			{
				 let titleField = document.getElementById(postTitleFields[i]); 
				 if (null !== titleField)
				 {
					 titleField.value = newTitle;
				 }
			}
		 }

		if (null !== attachmentAlt)
		{
			if (attachmentAlt.dataset.shortpixelAlt && attachmentAlt.dataset.shortpixelAlt != item_id)
			{
				console.log('AttachmentAlt not ' + item_id); 
				return;
			}
			
			var data = {
				id: item_id,
				type: 'media',
				screen_action: 'ai/getAltData',
			}
			data.callback = 'spaatg.AttachAiInterface';
			this.processor.AjaxRequest(data);

			window.addEventListener('spaatg.AttachAiInterface', this.AttachAiInterface.bind(this), {once: true});
		}
	}

	GetPageAttachmentAlt()
	{
		for (var i = 0; i < this.altInputNames.length; i++)
			{
				var attachmentAlt = document.getElementById(this.altInputNames[i]);
				if (attachmentAlt !== null)
				{
					return attachmentAlt;
				} 	
			}
		return null;
	}

	RenderItemView(e) {
		e.preventDefault();
		var data = e.detail;
		if (data.media) {
			var id = data.media.id;

			var element = document.getElementById('spaatg-data-' + id);
			if (element !== null) // Could be other page / not visible / whatever.
				element.outerHTML = data.media.itemView;
			else {
				console.error('Render element not found');
			}
		}
		else {
			console.error('Data not found - RenderItemview on media screen');
		}
		return false; // callback shouldn't do more, see processor.
	}

		HandleImage(resultItem, type) {
			var res = super.HandleImage(resultItem, type);
			var fileStatus = this.processor.fStatus[resultItem.fileStatus];
			var apiName = (typeof resultItem.apiName !== 'undefined') ? resultItem.apiName : 'ai';

			if (fileStatus == 'FILE_DONE' && apiName == 'ai')
			{
			this.UpdateGutenBerg(resultItem);
		}
	}

	// Check the Gallery popup on Media Library 
	ListenGallery() {
		var self = this;
		var next_item_run_process = false; 

		if (this.settings.hide_spaatg_in_popups)
		{
			return;
		}

		if (typeof wp.media === 'undefined') {
			return;
		}

		// This taken from S3-offload / media.js /  Grid media gallery
		if (typeof wp.media.view.Attachment.Details.TwoColumn !== 'undefined') {
			var detailsColumn = wp.media.view.Attachment.Details.TwoColumn; // Media library grid.
			var twoCol = true;
			var opener = 'gallery'; 
		}
		else {
			var detailsColumn = wp.media.view.Attachment.Details; // Gutenberg
			var twoCol = false;
			var opener = 'gutenberg'; 
		}

		var extended = detailsColumn.extend({
			render: function () {
				detailsColumn.prototype.render.apply(this); // Render Parent

				if (typeof this.fetchSPIOData === 'function') {
					let attach_id = this.model.get('id');

					if (typeof attach_id !== 'undefined')
					{
						if (true === next_item_run_process )
						{
							window.SPAATGProcessor.SetInterval(-1);
							window.SPAATGProcessor.RunProcess();
							next_item_run_process = false; 
						}
						else
						{
						this.fetchSPIOData(attach_id);
						this.spioBusy = true; // Note if this system turns out not to work, the perhaps render empties all if first was painted, second cancelled?
						}
					}
					else if (true == this.model.get('uploading'))
					{
						next_item_run_process = true; 
						console.log('Upload Start Detected');
					}
					else
					{
						console.log('Id not found on render');
					}
				}

				return this;
			},

			fetchSPIOData: function (id) {
				var data = {};
				data.id = id;
				data.type = self.type;
				data.callback = 'spaatg.MediaRenderView';

				if (typeof this.spioBusy !== 'undefined' && this.spioBusy === true) {
					return;
				}

				window.addEventListener('spaatg.MediaRenderView', this.renderSPIOView.bind(this), { 'once': true });
				self.processor.LoadItemView(data);
			},

			renderSPIOView: function (e, timed) {
				this.spioBusy = false;
				if (!e.detail || !e.detail.media || !e.detail.media.itemView) {
					return;
				}

				var item_id = e.detail.media.id; 

				var $spSpace = this.$el.find('.attachment-info .details');
				if ($spSpace.length === 0 && (typeof timed === 'undefined' || timed < 5)) {
					// It's possible the render is slow or blocked by other plugins. Added a delay and retry bit later to draw.
					if (typeof timed === 'undefined') {
						var timed = 0;
					}
					else {
						timed++;
					}
					setTimeout(function () { this.renderSPIOView(e, timed) }.bind(this), 1000);
				}

				var html = this.doSPIORow(item_id, e.detail.media.itemView);
				$spSpace.after(html);

				self.UpdateGeneratedAltPanels(item_id, self.GetAttachmentAltValue(item_id));
				self.FetchAltView(undefined, item_id); 

			},
			doSPIORow: function (item_id) {
				var html = '';
				html += '<div class="shortpixel-popup-info shortpixel-generated-alt-panel" data-spaatg-generated-alt="' + item_id + '">';
				html += '<label class="name">' + self.settings.alt_text_label + '</label>';
				html += '<div class="shortpixel-generated-alt-content is-empty">' + self.settings.alt_text_loading + '</div>';
				html += '</div>';
				return html;
			},
			 
			editAttachment: function (event) {
				event.preventDefault();
				detailsColumn.prototype.editAttachment.apply(this, [event]);
			}
		});

		if (true === twoCol) {
			wp.media.view.Attachment.Details.TwoColumn = extended; //wpAttachmentDetailsTwoColumn;
		}
		else {
			wp.media.view.Attachment.Details = extended;
		}
	}

	// It's not possible via hooks / server-side, so attach the AI interface HTML to where it should be attached. 
	AttachAiInterface(event)
	{
		
		var data = event.detail.media; 	
		var item_id = data.item_id; 
		if (typeof data === 'undefined')
		{
			console.log('Error on ai interface!', data);
			return false;
		}
		var element = this.GetPageAttachmentAlt();

		if (null == element)
		{
			console.warn('Could not attach ID interface here! '); 
			return false; 
		}

		var wrapper = document.getElementById('spaatg-ai-wrapper-' + item_id);

		if (null !== wrapper)
		{
			wrapper.remove();
		}

		var editMediaActions = document.querySelector('[data-spaatg-ai-actions="' + item_id + '"]');

		if (editMediaActions !== null && typeof data.snippet === 'string')
		{
			editMediaActions.innerHTML = this.PrepareAiSnippet(data.snippet, item_id, 'metabox');
		}

		if (typeof data.snippet === 'string' && data.snippet.length > 0)
		{
			wrapper = document.createElement('div');
			wrapper.id = 'spaatg-ai-wrapper-' + item_id;
			wrapper.classList.add('shortpixel-ai-interface', element.getAttribute('id'));
			wrapper.innerHTML = this.PrepareAiSnippet(data.snippet, item_id, 'inline');
			element.after(wrapper);
		}

		element.dataset.shortpixelAlt = data.item_id;		
		this.UpdateGeneratedAltPanels(data.item_id, this.ResolveAltText(data));

		if (data.generated && typeof data.generated.alt !== 'undefined')
			element.value = data.generated.alt;

	}

	PrepareAiSnippet(snippet, item_id, context)
	{
		var originalId = 'shortpixel-ai-messagebox-' + item_id;
		var replacementId = ('metabox' === context) ? 'shortpixel-ai-messagebox-box-' + item_id : originalId;
		var replacement = 'id="' + replacementId + '" data-spaatg-ai-messagebox="' + item_id + '"';
		var idPattern = new RegExp('id=([\\\'"])' + originalId + '\\1');

		return snippet.replace(idPattern, replacement);
	}

	GetAttachmentAltValue(item_id)
	{
		var attachmentAlt = this.GetPageAttachmentAlt();
		if (attachmentAlt === null)
		{
			return '';
		}

		if (attachmentAlt.dataset.shortpixelAlt && attachmentAlt.dataset.shortpixelAlt != item_id)
		{
			return '';
		}

		return (typeof attachmentAlt.value !== 'undefined') ? attachmentAlt.value : '';
	}

	ResolveAltText(data)
	{
		if (data && data.generated && typeof data.generated.alt !== 'undefined' && data.generated.alt.length > 0)
		{
			return data.generated.alt;
		}

		if (data && data.current && typeof data.current.alt !== 'undefined' && data.current.alt.length > 0)
		{
			return data.current.alt;
		}

		if (data && typeof data.alt !== 'undefined' && data.alt.length > 0)
		{
			return data.alt;
		}

		return '';
	}

	UpdateGeneratedAltPanels(item_id, altText)
	{
		var panels = document.querySelectorAll('[data-spaatg-generated-alt="' + item_id + '"]');
		if (panels.length === 0)
		{
			return;
		}

		panels.forEach(function (panel) {
			var content = panel.querySelector('.shortpixel-generated-alt-content');
			if (content === null)
			{
				return;
			}

			if (typeof altText === 'string' && altText.length > 0)
			{
				content.textContent = altText;
				content.classList.remove('is-empty');
				panel.classList.remove('is-empty');
			}
			else
			{
				content.textContent = this.settings.alt_text_empty;
				content.classList.add('is-empty');
				panel.classList.add('is-empty');
			}
		}.bind(this));
	}

	ListenGutenberg()
	{

		var self = this; 

		if (typeof wp.data == 'undefined')
		{
			return;
		}

		wp.data.subscribe(() => {
			if (wp.data.select('core')) {
				const { getSelectedBlock } = wp.data.select('core/block-editor');
		
				const block = getSelectedBlock();
			
				if (block && block.name === 'core/image') {
					const imageId = block.attributes.id; // Get the image ID
		
					if (imageId) {
		
						if (self.gutenCheck.indexOf(imageId) === -1)
						{
						
							window.SPAATGProcessor.SetInterval(-1);
							window.SPAATGProcessor.RunProcess();
						
							self.gutenCheck.push(imageId);
						}
						else
						{
						
						}
		
					}
				}
			}
		});
	}

	UpdateGutenBerg(resultItem)
	{
		
		var attach_id = resultItem.item_id; 
		var aiData = resultItem.aiData; 
		
		if (! wp.data || ! wp.data.select('core'))
		{
			return false; 
		}

		let blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		for (let i = 0; i < blocks.length; i++)
		{
			let block = blocks[i];

			 if (block.attributes.id == attach_id)
			 {
				let clientId = block.clientId;

				console.log('DATA DISPATCH ', clientId, aiData);				
				wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, 
					aiData );

			 }
		}
	}

} // class
