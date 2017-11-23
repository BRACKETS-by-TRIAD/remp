package controller

import (
	"encoding/json"
	"time"

	"github.com/goadesign/goa"
	"github.com/pkg/errors"
	"gitlab.com/remp/remp/Beam/go/cmd/segments/app"
	"gitlab.com/remp/remp/Beam/go/model"
)

// SegmentController implements the segment resource.
type SegmentController struct {
	*goa.Controller
	SegmentStorage model.SegmentStorage
}

// NewSegmentController creates a segment controller.
func NewSegmentController(service *goa.Service, segmentStorage model.SegmentStorage) *SegmentController {
	return &SegmentController{
		Controller:     service.NewController("SegmentController"),
		SegmentStorage: segmentStorage,
	}
}

// List runs the list action.
func (c *SegmentController) List(ctx *app.ListSegmentsContext) error {
	sc, err := c.SegmentStorage.List()
	if err != nil {
		return err
	}
	return ctx.OK((SegmentCollection)(sc).ToMediaType())
}

// Check runs the check action.
func (c *SegmentController) Check(ctx *app.CheckSegmentsContext) error {
	s, ok, err := c.SegmentStorage.Get(ctx.SegmentCode)
	if err != nil {
		return err
	}
	if !ok {
		return ctx.NotFound()
	}
	now := time.Now()

	// unmarshal fields and cache
	var ro model.RuleOverrides
	if ctx.Fields != nil {
		overrides := make(map[string]string)
		if err := json.Unmarshal([]byte(*ctx.Fields), &overrides); err != nil {
			return errors.Wrap(err, "invalid format of fields JSON string")
		}
		ro.Fields = overrides
	}
	var cache model.SegmentCache
	if ctx.Cache != nil {
		if err := json.Unmarshal([]byte(*ctx.Cache), &cache); err != nil {
			return errors.Wrap(err, "invalid format of cache JSON string")
		}
	}

	// unset invalidated elements
	for id, c := range cache {
		if c.SyncedAt.Before(now.Add(-1 * time.Hour)) {
			delete(cache, id)
		}
	}

	ok, cache, err = c.SegmentStorage.Check(s, ctx.UserID, now, cache, ro)
	if err != nil {
		return err
	}
	er := c.SegmentStorage.EventRules()

	return ctx.OK(&app.SegmentCheck{
		Check:      ok,
		Cache:      (SegmentCache(cache)).ToMediaType(),
		EventRules: er,
	})
}

// Users runs the users action.
func (c *SegmentController) Users(ctx *app.UsersSegmentsContext) error {
	s, ok, err := c.SegmentStorage.Get(ctx.SegmentCode)
	if err != nil {
		return err
	}
	if !ok {
		return ctx.NotFound()
	}
	ro := model.RuleOverrides{}
	if ctx.Fields != nil {
		overrides := make(map[string]string)
		if err := json.Unmarshal([]byte(*ctx.Fields), &overrides); err != nil {
			return errors.Wrap(err, "invalid format of fields JSON string")
		}
		ro.Fields = overrides
	}
	uc, err := c.SegmentStorage.Users(s, time.Now(), ro)
	if err != nil {
		return err
	}
	return ctx.OK(uc)
}
