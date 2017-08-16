package model

import (
	"errors"
	"fmt"
	"log"
	"time"

	"github.com/influxdata/influxdb/client/v2"
	"gitlab.com/remp/remp/Beam/go/influxquery"
)

// Options represent filter options for event-related calls.
type EventOptions struct {
	UserID     string
	Action     string
	Category   string
	TimeAfter  time.Time
	TimeBefore time.Time
}

type Event struct {
	Category  string
	Action    string
	Token     string
	Time      time.Time
	Host      string
	IP        string
	UserID    string
	URL       string
	UserAgent string
}

type EventCollection []*Event

type EventStorage interface {
	// Count returns number of events matching the filter defined by EventOptions.
	Count(o EventOptions) (int, error)
	// List returns list of all events based on given EventOptions.
	List(o EventOptions) (EventCollection, error)
	// Categories lists all tracked categories.
	Categories() ([]string, error)
	// Actions lists all tracked actions under the given category.
	Actions(category string) ([]string, error)
}

type EventDB struct {
	DB *InfluxDB
}

// Count returns number of events matching the filter defined by EventOptions.
func (eDB *EventDB) Count(o EventOptions) (int, error) {
	builder := eDB.DB.QueryBuilder.Select("count(value)").From("events")
	builder = eDB.addQueryFilters(builder, o)

	q := client.Query{
		Command:  builder.Build(),
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return 0, err
	}
	if response.Error() != nil {
		return 0, response.Error()
	}

	// no data returned
	if len(response.Results[0].Series) == 0 {
		return 0, nil
	}

	// process response
	return eDB.DB.Count(response)
}

// List returns list of all events based on given EventOptions.
func (eDB *EventDB) List(o EventOptions) (EventCollection, error) {
	builder := eDB.DB.QueryBuilder.Select("*").From("events")
	builder = eDB.addQueryFilters(builder, o)

	q := client.Query{
		Command:  builder.Build(),
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return nil, err
	}
	if response.Error() != nil {
		return nil, response.Error()
	}

	ec := EventCollection{}

	// no data returned
	if len(response.Results[0].Series) == 0 {
		return ec, nil
	}

	for _, s := range response.Results[0].Series {
		for idx := range s.Values {
			ir := influxquery.NewInfluxResult(s, idx)
			e, err := eventFromInfluxResult(ir)
			if err != nil {
				return nil, err
			}
			ec = append(ec, e)
		}
	}

	return ec, nil
}

func (eDB *EventDB) Categories() ([]string, error) {
	q := client.Query{
		Command:  `SHOW TAG VALUES WITH KEY = "category"`,
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return nil, err
	}
	if response.Error() != nil {
		return nil, response.Error()
	}

	categories := []string{}
	if len(response.Results[0].Series) == 0 {
		return categories, nil
	}
	for _, val := range response.Results[0].Series[0].Values {
		strVal, ok := val[1].(string)
		if !ok {
			return nil, errors.New("unable to convert influx result value to string")
		}
		categories = append(categories, strVal)
	}
	return categories, nil
}

func (eDB *EventDB) Actions(category string) ([]string, error) {
	q := client.Query{
		Command:  fmt.Sprintf(`SHOW TAG VALUES WITH KEY = "action" WHERE category =~ /%s/`, category),
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return nil, err
	}
	if response.Error() != nil {
		return nil, response.Error()
	}

	actions := []string{}
	if len(response.Results[0].Series) == 0 {
		return actions, nil
	}
	for _, val := range response.Results[0].Series[0].Values {
		strVal, ok := val[1].(string)
		if !ok {
			return nil, errors.New("unable to convert influx result value to string")
		}
		actions = append(actions, strVal)
	}
	return actions, nil
}

func (eDB *EventDB) addQueryFilters(builder influxquery.Builder, o EventOptions) influxquery.Builder {
	if o.UserID != "" {
		builder.Where(fmt.Sprintf("user_id = '%s'", o.UserID))
	}
	if o.Category != "" {
		builder.Where(fmt.Sprintf("category = '%s'", o.Category))
	}
	if o.Action != "" {
		builder.Where(fmt.Sprintf("action = '%s'", o.Action))
	}
	if !o.TimeAfter.IsZero() {
		builder.Where(fmt.Sprintf("time >= %d", o.TimeAfter.UnixNano()))
	}
	if !o.TimeBefore.IsZero() {
		builder.Where(fmt.Sprintf("time < %d", o.TimeBefore.UnixNano()))
	}
	return builder
}

func eventFromInfluxResult(ir *influxquery.Result) (*Event, error) {
	log.Printf("DEBUG: %#v\n", ir)
	category, ok := ir.StringValue("category")
	if !ok {
		return nil, errors.New("unable to map Category to influx result column")
	}
	action, ok := ir.StringValue("action")
	if !ok {
		return nil, errors.New("unable to map Action to influx result column")
	}
	token, ok := ir.StringValue("token")
	if !ok {
		return nil, errors.New("unable to map Token to influx result column")
	}
	t, ok, err := ir.TimeValue("time")
	if err != nil {
		return nil, err
	}
	if !ok {
		return nil, errors.New("unable to map Time to influx result column")
	}
	event := &Event{
		Category: category,
		Action:   action,
		Token:    token,
		Time:     t,
	}

	host, ok := ir.StringValue("host")
	if ok {
		event.Host = host
	}
	ip, ok := ir.StringValue("ip")
	if ok {
		event.IP = ip
	}
	userID, ok := ir.StringValue("user_id")
	if ok {
		event.UserID = userID
	}
	url, ok := ir.StringValue("url")
	if ok {
		event.URL = url
	}
	userAgent, ok := ir.StringValue("user_agent")
	if ok {
		event.UserAgent = userAgent
	}

	return event, nil
}
