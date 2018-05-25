package controller

import (
	"github.com/goadesign/goa"
	"gitlab.com/remp/remp/Beam/go/cmd/segments/app"
	"gitlab.com/remp/remp/Beam/go/model"
)

// EventController implements the event resource.
type EventController struct {
	*goa.Controller
	EventStorage model.EventStorage
}

// NewEventController creates an event controller.
func NewEventController(service *goa.Service, es model.EventStorage) *EventController {
	return &EventController{
		Controller:   service.NewController("EventController"),
		EventStorage: es,
	}
}

// Count runs the count action.
func (c *EventController) Count(ctx *app.CountEventsContext) error {
	o := model.AggregateOptions{
		Action:   ctx.Action,
		Category: ctx.Category,
	}

	for _, val := range ctx.Payload.FilterBy {
		fb := &model.FilterBy{
			Tag:    val.Tag,
			Values: val.Values,
		}
		o.FilterBy = append(o.FilterBy, fb)
	}

	o.GroupBy = ctx.Payload.GroupBy

	if ctx.Payload.TimeAfter != nil {
		o.TimeAfter = *ctx.Payload.TimeAfter
	}
	if ctx.Payload.TimeBefore != nil {
		o.TimeBefore = *ctx.Payload.TimeBefore
	}

	crc, ok, err := c.EventStorage.Count(o)
	if err != nil {
		return err
	}
	if !ok {
		cr := model.CountRow{
			Tags:  make(map[string]string),
			Count: 0,
		}
		crc = model.CountRowCollection{}
		crc = append(crc, cr)
	}

	acrc := CountRowCollection(crc).ToMediaType()
	return ctx.OK(acrc)
}

// List runs the list action.
func (c *EventController) List(ctx *app.ListEventsContext) error {
	aggOptions := aggregateOptionsFromEventsOptions(ctx.Payload.Conditions)
	o := model.ListOptions{
		AggregateOptions: aggOptions,
		SelectFields:     ctx.Payload.SelectFields,
	}

	erc, err := c.EventStorage.List(o)
	if err != nil {
		return err
	}
	mt, err := EventRowCollection(erc).ToMediaType()
	if err != nil {
		return err
	}
	return ctx.OK(mt)
}

// Categories runs the categories action.
func (c *EventController) Categories(ctx *app.CategoriesEventsContext) error {
	categories, err := c.EventStorage.Categories()
	if err != nil {
		return err
	}
	return ctx.OK(categories)
}

// Actions runs the action action. :)
func (c *EventController) Actions(ctx *app.ActionsEventsContext) error {
	actions, err := c.EventStorage.Actions(ctx.Category)
	if err != nil {
		return err
	}
	return ctx.OK(actions)
}

// Users runs the users action.
func (c *EventController) Users(ctx *app.UsersEventsContext) error {
	users, err := c.EventStorage.Users()
	if err != nil {
		return err
	}
	return ctx.OK(users)
}

// aggregateOptionsFromEventsOptions converts payload data to AggregateOptions.
func aggregateOptionsFromEventsOptions(payload *app.EventOptionsPayload) model.AggregateOptions {
	var o model.AggregateOptions

	for _, val := range payload.FilterBy {
		fb := &model.FilterBy{
			Tag:    val.Tag,
			Values: val.Values,
		}
		o.FilterBy = append(o.FilterBy, fb)
	}

	o.GroupBy = payload.GroupBy
	if payload.TimeAfter != nil {
		o.TimeAfter = *payload.TimeAfter
	}
	if payload.TimeBefore != nil {
		o.TimeBefore = *payload.TimeBefore
	}

	return o
}
