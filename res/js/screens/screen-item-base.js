'use strict';

class SPAATGScreenItemBase extends SPAATGScreenBase {

	type; // media / custom
	currentMessage = '';

	constructor(MainScreen, processor) {
		super(MainScreen, processor);
	}

	Init() {
		super.Init();

		window.addEventListener('spaatg.' + this.type + '.resumeprocessing', this.processor.ResumeProcess.bind(this.processor));
		window.addEventListener('spaatg.RenderItemView', this.RenderItemView.bind(this));

	}

	/* ResultItem : Object of result output coming from QueueItem result() function . Mostly passed via AjaxController Json output.
	*/
	HandleImage(resultItem, type) {

		if (type != this.type)  // We don't eat that here.
		{
			return false;
		}

		// This is final, not more messing with this. In results (multiple) defined one level higher than result object, if single, it's in result.
		var item_id = resultItem.item_id;
		var message = resultItem.message;
		
		// This is the reporting element ( all the data, via getItemView? )
		var element = this.GetElement(resultItem, 'data');
		var apiName = (typeof resultItem.apiName !== 'undefined') ? resultItem.apiName : 'ai';
		if (apiName !== 'ai')
		{
			return false;
		}

		var isError = false;
		if (resultItem.is_error == true)
			isError = true;

		if (typeof message !== 'undefined' && null !== element)
		{
			this.UpdateMessage(resultItem, message, isError);
		}

		if (element !== null) {
			var fileStatus = this.processor.fStatus[resultItem.fileStatus];
			var isAttachmentEditScreen = (typeof this.settings !== 'undefined' && this.settings.wp_screen_id == 'attachment');
			if ((fileStatus == 'FILE_DONE' || true == resultItem.is_done) && false === isAttachmentEditScreen)
			{
				this.processor.LoadItemView({ id: item_id, type: type });
			}
		}

		if (typeof resultItem.aiData !== 'undefined')
		{
			 this.FetchAltView(resultItem.aiData, item_id);
		}

		return false;
	}

	// @todo here also update le message. 
	UpdateMessage(resultItem, message, isError) {

		if (typeof resultItem !== 'object')
		{	
			// Not all interface get passed resultItem. Adapt.
			 resultItem = {
				'id': resultItem,
				'item_id': resultItem,
			 }
			 console.error('updatemessge ref wrong');
		}

		var element = this.GetElement(resultItem, 'message');
		var elements = this.GetMessageElements(resultItem, element);

		if (typeof isError === 'undefined')
			isError = false;

		this.currentMessage = message;

		if (elements.length > 0) {
			elements.forEach(function (messageElement) {
				if (messageElement.classList.contains('error'))
					messageElement.classList.remove('error');

				messageElement.innerHTML = message;
				var visibleMessage = String(message).replace(/&nbsp;/g, '').trim();

				if (visibleMessage.length > 0)
					messageElement.classList.add('has-message');
				else
					messageElement.classList.remove('has-message');

				if (isError)
					messageElement.classList.add('error');
			});
		}
		else {
			this.processor.Debug('Update Message Column not found - ' + resultItem.id);
		}
	}

	GetMessageElements(resultItem, fallbackElement)
	{
		var apiName = (typeof resultItem.apiName !== 'undefined') ? resultItem.apiName : 'ai';
		var id = (typeof resultItem.item_id !== 'undefined') ? resultItem.item_id : resultItem.id;
		var elements = [];

		if (apiName == 'ai' && typeof id !== 'undefined')
		{
			elements = Array.prototype.slice.call(document.querySelectorAll('[data-spaatg-ai-messagebox="' + id + '"]'));
		}

		if (elements.length === 0 && fallbackElement !== null)
		{
			elements.push(fallbackElement);
		}

		return elements;
	}

	/**
	 * 
	 * @param {mixed} responseItem 
	 * @param {string} dataType  [message|data]
	 */
	GetElement(resultItem, dataType)
	{
		 var id = (typeof resultItem.item_id !== 'undefined') ? resultItem.item_id : resultItem.id; 
		 var apiName = (typeof resultItem.apiName !== 'undefined') ? resultItem.apiName : 'ai';
		 var createIfMissing = false; 

		 if (apiName == 'ai')
		 {
			// Edit media view 
			var elementName = 'shortpixel-ai-messagebox-' + id; 
			var element = document.getElementById(elementName);

			if (null == element) // List-view
			{
				var aiMessageBoxes = document.querySelectorAll('[data-spaatg-ai-messagebox="' + id + '"]');
				if (aiMessageBoxes.length > 0)
				{
					return aiMessageBoxes[0];
				}

				var elementName = 'shortpixel-message-' + id;  // see if this works better
				createIfMissing = true; 
			}
		}
			
		 var element = document.getElementById(elementName);
		 if (element === null)
		 {
			  if (false === createIfMissing)
			  {
				 return null; 
			  }

			  var parent = document.getElementById('spaatg-data-' + id);
			  if (parent !== null) {
				  var element = document.createElement('div');
				  element.classList.add('message');
				  element.setAttribute('id', 'shortpixel-message-' + id);
				  parent.parentNode.insertBefore(element, parent.nextSibling);
			  }

		 }

		 return element; 

	}

	// Show a message that an action has started.
	SetMessageProcessing(id, apiName) {
		this.DisableItemActions(id);

		if (typeof apiName === 'undefined')
		{
			var apiName = 'ai';
		}

		if (apiName == 'ai')
		{
			var message = this.strings.startActionAI;
		}

		
		var item = {
			item_id: id, 
			apiName: apiName,
		};
		this.UpdateMessage(item, message);
	}

	DisableItemActions(id)
	{
		var actions = document.querySelectorAll('[data-spaatg-action-id="' + id + '"], [data-spaatg-ai-action-id="' + id + '"]');

		for (var i = 0; i < actions.length; i++)
		{
			actions[i].classList.add('disabled');
			actions[i].setAttribute('aria-disabled', 'true');
			actions[i].setAttribute('tabindex', '-1');
			actions[i].setAttribute('href', 'javascript:void(0)');
			actions[i].dataset.spaatgDisabled = 'queue';
		}
	}

	UpdateStats(stats, type) {
		// for now, since we process both, only update the totals in tooltip.
		if (type !== 'total')
			return;

		var waiting = stats.in_queue + stats.in_process;
		this.processor.tooltip.RefreshStats(waiting);
	}

	GeneralResponses(responses) {
		var self = this;

		if (responses.length == 0)  // no responses.
			return;

		var shownId = []; // prevent the same ID from creating multiple tooltips. There will be punishment for this.

		responses.forEach(function (element, index) {

			if (element.id) {
				if (shownId.indexOf(element.id) > -1) {
					return; // skip
				}
				else {
					shownId.push(element.id);
				}
			}

			var message = element.message;
			if (element.filename)
				message += ' - ' + element.filename;

			self.processor.tooltip.AddNotice(message);
			if (self.processor.rStatus[element.code] == 'RESPONSE_ERROR') {

				if (element.id) {
					var message = self.currentMessage;
					self.UpdateMessage(element.id, message + '<br>' + element.message);
					self.currentMessage = message; // don't overwrite with this, to prevent echo.
				}
				else {
					var errorBox = document.getElementById('shortpixel-errorbox');
					if (errorBox) {
						var error = document.createElement('div');
						error.classList.add('error');
						error.innerHTML = element.message;
						errorBox.append(error);
					}
				}
			}
		});

	}

	// HandleItemError is handling from results / result, not ResponseController. Check if it has negative effects it's kinda off now.
	HandleItemError(result) {
		if (result.message && result.item_id) {
			this.UpdateMessage(result, result.message, true);
		}

	}

	CancelOptimizeItem(id) {
		var data = {};
		data.id = id;
		data.type = this.type;
		data.screen_action = 'cancelOptimize';
		// AjaxRequest should return result, which will go through Handleresponse, then LoaditemView.

		this.processor.AjaxRequest(data);
	}

	RequestAlt(id, options) {
		options = options || {};
		var data = {
			id: id,
			type: this.type,
			'screen_action': 'ai/requestalt',
		}

		if (typeof options.aiPreserve !== 'undefined')
		{
			data.aiPreserve = options.aiPreserve ? true : false;
		}

		if (!this.processor.CheckActive())
			data.callback = 'spaatg.' + this.type + '.resumeprocessing';

		this.SetMessageProcessing(id, 'ai');
		this.processor.AjaxRequest(data);
	}

	UndoAlt(id, action_type, options)
	{
		options = options || {};
		var data = {
			id: id,
			type: this.type,
			'screen_action': 'ai/undoAlt',
			'action_type' : action_type, 
			'callback': 'spaatg.HandleUndoAlt',
		}

		if (typeof options.aiPreserve !== 'undefined')
		{
			data.aiPreserve = options.aiPreserve ? true : false;
		}

		window.addEventListener('spaatg.HandleUndoAlt', function (event) {
			var data = event.detail.media;
			var original = data.current; 
	
			if ('redo' == action_type)
			{
				if (!this.processor.CheckActive())
				{
					let ev = new Event('spaatg.' + this.type + '.resumeprocessing');
					window.dispatchEvent(ev);

				}
			}
			this.FetchAltView(original,id);

		}.bind(this), {once: true});

	/*	if (!this.processor.CheckActive())
			data.callback = 'spaatg.' + this.type + '.resumeprocessing'; */

		//this.SetMessageProcessing(id, 'ai');
		this.DisableItemActions(id);
		this.processor.AjaxRequest(data);
	}

	FetchAltView()
	{
		 console.error('not implemented for this view!');
	}
	
	AttachAiInterface()
	{
		 console.error('not implemented for this view!');
	}

} // class
