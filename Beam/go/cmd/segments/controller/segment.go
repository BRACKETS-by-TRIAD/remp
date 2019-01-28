package controller

import (
	"encoding/json"
	"fmt"
	"time"

	"github.com/goadesign/goa"
	"github.com/pkg/errors"
	"gitlab.com/remp/remp/Beam/go/cmd/segments/app"
	"gitlab.com/remp/remp/Beam/go/model"
)

// SegmentType represents type of segment (source of data used for segment)
type SegmentType int

// Enum of available segment types
const (
	UserSegment SegmentType = iota + 1
	BrowserSegment
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

// CheckUser runs the check_user action.
func (c *SegmentController) CheckUser(ctx *app.CheckUserSegmentsContext) error {
	sc, ok, err := c.handleCheck(UserSegment, ctx.SegmentCode, ctx.UserID, ctx.Fields, ctx.Cache)
	if err != nil {
		return err
	}
	if !ok {
		return ctx.NotFound()
	}
	return ctx.OK(sc)
}

// CheckBrowser runs the check_browser action.
func (c *SegmentController) CheckBrowser(ctx *app.CheckBrowserSegmentsContext) error {
	sc, ok, err := c.handleCheck(BrowserSegment, ctx.SegmentCode, ctx.BrowserID, ctx.Fields, ctx.Cache)
	if err != nil {
		return err
	}
	if !ok {
		return ctx.NotFound()
	}
	return ctx.OK(sc)
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

// CreateOrUpdate runs the create_or_update action.
func (c *SegmentController) CreateOrUpdate(ctx *app.CreateOrUpdateSegmentsContext) error {
	if ctx.ID != nil {
		return c.handleUpdate(ctx)
	}
	return c.handleCreate(ctx)
}

// handleCreate handles creation of Segment.
func (c *SegmentController) handleCreate(ctx *app.CreateOrUpdateSegmentsContext) error {
	p := ctx.Payload

	criteriaJSON, err := json.Marshal(ctx.Payload.Criteria)
	if err != nil {
		return errors.Wrap(err, "unable to marshal segment's criteria payload")
	}

	// TODO: maybe code should be also part of payload? check with CRM
	code, err := model.Webalize(p.Name)
	if err != nil {
		return err
	}

	sd := model.SegmentData{
		Name:           p.Name,
		Code:           code,
		Active:         true,
		SegmentGroupID: p.GroupID,
		Criteria:       string(criteriaJSON),
	}
	s, err := c.SegmentStorage.Create(sd)
	if err != nil {
		return err
	}

	return ctx.OK((*Segment)(s).ToMediaType())
}

// handleUpdate handles update of Segment.
func (c *SegmentController) handleUpdate(ctx *app.CreateOrUpdateSegmentsContext) error {
	p := ctx.Payload

	criteriaJSON, err := json.Marshal(ctx.Payload.Criteria)
	if err != nil {
		return errors.Wrap(err, "unable to marshal segment's criteria payload")
	}
	sd := model.SegmentData{
		Name:           p.Name,
		Active:         true,
		SegmentGroupID: p.GroupID,
		Criteria:       string(criteriaJSON),
	}
	s, ok, err := c.SegmentStorage.Update(*ctx.ID, sd)
	if err != nil {
		return err
	}
	if !ok {
		return ctx.NotFound()
	}
	return ctx.OK((*Segment)(s).ToMediaType())
}

// handleCheck determines whether provided identifier is part of segment based on given segment type.
func (c *SegmentController) handleCheck(segmentType SegmentType, segmentCode, identifier string, fields, cache *string) (*app.SegmentCheck, bool, error) {
	s, ok, err := c.SegmentStorage.Get(segmentCode)
	if err != nil {
		return nil, false, err
	}
	if !ok {
		return nil, false, nil
	}
	now := time.Now()

	// unmarshal fields and cache
	var ro model.RuleOverrides
	if fields != nil {
		overrides := make(map[string]string)
		if err := json.Unmarshal([]byte(*fields), &overrides); err != nil {
			return nil, false, errors.Wrap(err, "invalid format of fields JSON string")
		}
		ro.Fields = overrides
	}
	var segmentCache model.SegmentCache
	if cache != nil {
		if err := json.Unmarshal([]byte(*cache), &segmentCache); err != nil {
			return nil, false, errors.Wrap(err, "invalid format of cache JSON string")
		}
	}

	switch segmentType {
	case BrowserSegment:
		// unset invalidated elements, keeping the cache longer as single-browser doesn't need count syncing that often (no other devices affect the count)
		for id, c := range segmentCache {
			if c.SyncedAt.Before(now.Add(-24 * time.Hour)) {
				delete(segmentCache, id)
			}
		}
		segmentCache, ok, err = c.SegmentStorage.CheckBrowser(s, identifier, now, segmentCache, ro)
	case UserSegment:
		// unset invalidated elements, removing the cache after one hour to sync count which could include other user's devices
		for id, c := range segmentCache {
			if c.SyncedAt.Before(now.Add(-1 * time.Hour)) {
				delete(segmentCache, id)
			}
		}
		segmentCache, ok, err = c.SegmentStorage.CheckUser(s, identifier, now, segmentCache, ro)
	default:
		return nil, false, fmt.Errorf("unhandled segment type: %d", segmentType)
	}

	if err != nil {
		return nil, false, err
	}
	er := c.SegmentStorage.EventRules()
	of := c.SegmentStorage.OverridableFields()
	flags := c.SegmentStorage.Flags()

	return &app.SegmentCheck{
		Check:             ok,
		Cache:             (SegmentCache(segmentCache)).ToMediaType(),
		EventRules:        er,
		OverridableFields: of,
		Flags:             flags,
	}, true, nil
}
