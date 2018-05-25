package model

import (
	"errors"
	"fmt"
	"log"
	"reflect"

	"github.com/influxdata/influxdb/client/v2"
	"gitlab.com/remp/remp/Beam/go/influxquery"
)

// EventDB is Influx implementation of EventStorage.
type EventDB struct {
	DB               *InfluxDB
	categoriesCached []string
	actionsCached    map[string][]string
}

// Count returns number of events matching the filter defined by EventOptions.
func (eDB *EventDB) Count(o AggregateOptions) (CountRowCollection, bool, error) {

	builder := eDB.DB.QueryBuilder.Select("count(token)").From(`"` + TableEvents + `"`)
	builder = addAggregateQueryFilters(builder, o)

	q := client.Query{
		Command:  builder.Build(),
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return nil, false, err
	}
	if response.Error() != nil {
		return nil, false, response.Error()
	}

	// process response
	return eDB.DB.MultiGroupedCount(response)
}

// List returns list of all events based on given EventOptions.
func (eDB *EventDB) List(o EventOptions) (EventRowCollection, error) {
	// not implemented; the original implementation was non-functional
	return EventRowCollection{}, nil
}

// Categories lists all tracked categories.
func (eDB *EventDB) Categories() ([]string, error) {
	// try to load from cache first
	if ec := eDB.categoriesCached; len(ec) > 0 {
		return ec, nil
	}

	q := client.Query{
		Command:  `SHOW TAG VALUES FROM "` + TableEvents + `" WITH KEY = "category"`,
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

// Flags lists all available flags.
func (eDB *EventDB) Flags() []string {
	return []string{}
}

// Actions lists all tracked actions under the given category.
func (eDB *EventDB) Actions(category string) ([]string, error) {
	// try to load from cache first
	if ac, ok := eDB.actionsCached[category]; ok {
		return ac, nil
	}

	q := client.Query{
		Command:  fmt.Sprintf(`SHOW TAG VALUES FROM "`+TableEvents+`" WITH KEY = "action" WHERE category =~ /%s/`, category),
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

// Users lists all tracked users.
func (eDB *EventDB) Users() ([]string, error) {
	q := client.Query{
		Command:  `SHOW TAG VALUES FROM "` + TableEvents + `" WITH KEY = "user_id"`,
		Database: eDB.DB.DBName,
	}

	response, err := eDB.DB.Client.Query(q)
	if err != nil {
		return nil, err
	}
	if response.Error() != nil {
		return nil, response.Error()
	}

	users := []string{}
	if len(response.Results[0].Series) == 0 {
		return users, nil
	}
	for _, val := range response.Results[0].Series[0].Values {
		strVal, ok := val[1].(string)
		if !ok {
			return nil, errors.New("unable to convert influx result value to string")
		}
		users = append(users, strVal)
	}
	return users, nil
}

// Cache stores event categories and activities in memory.
func (eDB *EventDB) Cache() error {
	// cache categories
	oldc := eDB.categoriesCached
	eDB.categoriesCached = []string{} // cache niled so Categories() loads categories from DB
	cl, err := eDB.Categories()
	if err != nil {
		return err
	}
	eDB.categoriesCached = cl

	if !reflect.DeepEqual(oldc, eDB.categoriesCached) {
		log.Println("event categories cache reloaded")
	}

	// cache actions for each category
	olda := eDB.actionsCached
	eDB.actionsCached = make(map[string][]string) // cache niled so Actions() loads actions from DB
	for _, c := range cl {
		cal, err := eDB.Actions(c)
		if err != nil {
			return err
		}
		eDB.actionsCached[c] = cal
	}

	if !reflect.DeepEqual(olda, eDB.actionsCached) {
		log.Println("event actions cache reloaded")
	}

	return nil
}

func (eDB *EventDB) addQueryFilters(builder influxquery.Builder, o EventOptions) influxquery.Builder {
	if o.UserID != "" {
		builder = builder.Where(fmt.Sprintf("user_id = '%s'", o.UserID))
	}
	if o.Category != "" {
		builder = builder.Where(fmt.Sprintf("category = '%s'", o.Category))
	}
	if o.Action != "" {
		builder = builder.Where(fmt.Sprintf("action = '%s'", o.Action))
	}
	if !o.TimeAfter.IsZero() {
		builder = builder.Where(fmt.Sprintf("time >= %d", o.TimeAfter.UnixNano()))
	}
	if !o.TimeBefore.IsZero() {
		builder = builder.Where(fmt.Sprintf("time < %d", o.TimeBefore.UnixNano()))
	}
	return builder
}

func eventFromInfluxResult(ir *influxquery.Result) (*Event, error) {
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
