package model

import (
	"encoding/json"
	"fmt"
	"io"
	"strconv"

	"github.com/olivere/elastic"
	"github.com/pkg/errors"
)

// PageviewElastic is ElasticDB implementation of PageviewStorage.
type PageviewElastic struct {
	DB            *ElasticDB
	actionsCached map[string][]string
}

// Count returns number of Pageviews matching the filter defined by PageviewOptions.
func (pDB *PageviewElastic) Count(options AggregateOptions) (CountRowCollection, bool, error) {
	// action is not being tracked within separate measurements and we would get no records back
	// removing it before applying filter
	options.Action = ""

	search := pDB.DB.Client.Search().
		Index("pageviews").
		Type("_doc").
		Size(0) // return no specific results

	search, err := pDB.DB.addSearchFilters(search, "pageviews", options)
	if err != nil {
		return nil, false, err
	}

	search, err = pDB.DB.addGroupBy(search, "pageviews", options, nil)
	if err != nil {
		return nil, false, err
	}

	// get results
	result, err := search.Do(pDB.DB.Context)
	if err != nil {
		return nil, false, err
	}

	if len(options.GroupBy) == 0 {
		// extract simplified results (no aggregation)
		return CountRowCollection{
			CountRow{
				Count: int(result.Hits.TotalHits),
			},
		}, true, nil
	}

	// extract results
	return pDB.DB.countRowCollectionFromAggregations(result, options)
}

// Sum returns number of Pageviews matching the filter defined by AggregateOptions.
func (pDB *PageviewElastic) Sum(options AggregateOptions) (SumRowCollection, bool, error) {
	extras := make(map[string]elastic.Aggregation)
	targetAgg := fmt.Sprintf("%s_sum", options.Action)
	extras[targetAgg] = elastic.NewSumAggregation().Field(options.Action)

	// action is not being tracked within separate measurements and we would get no records back
	// removing it before applying filter
	options.Action = ""

	search := pDB.DB.Client.Search().
		Index("pageviews").
		Type("_doc").
		Size(0) // return no specific results

	search, err := pDB.DB.addSearchFilters(search, "pageviews", options)
	if err != nil {
		return nil, false, err
	}

	search, err = pDB.DB.addGroupBy(search, "pageviews", options, extras)
	if err != nil {
		return nil, false, err
	}

	// get results
	result, err := search.Do(pDB.DB.Context)
	if err != nil {
		return nil, false, err
	}

	return pDB.DB.sumRowCollectionFromAggregations(result, options, targetAgg)
}

// List returns list of all Pageviews based on given PageviewOptions.
func (pDB *PageviewElastic) List(options ListOptions) (PageviewRowCollection, error) {
	var prc PageviewRowCollection

	scroll := pDB.DB.Client.Scroll("pageviews").
		Type("_doc").
		Size(1000)

	scroll, err := pDB.DB.addScrollFilters(scroll, "pageviews", options.AggregateOptions)
	if err != nil {
		return nil, err
	}

	// prepare PageviewRow buckets
	prBuckets := make(map[string]*PageviewRow)

	// get results
	for {
		results, err := scroll.Do(pDB.DB.Context)
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, errors.Wrap(err, "error while reading list data from elastic")
		}

		// Send the hits to the hits channel
		for _, hit := range results.Hits.Hits {
			// populate pageview for collection
			pv := &Pageview{}
			if err := json.Unmarshal(*hit.Source, pv); err != nil {
				return nil, errors.Wrap(err, "error reading pageview record from elastic")
			}

			// extract raw pageview data to build tags map
			rawPv := make(map[string]interface{})
			if err := json.Unmarshal(*hit.Source, &rawPv); err != nil {
				return nil, errors.Wrap(err, "error reading pageview record from elastic")
			}

			// we need to get string value for tags by type casting
			tags := make(map[string]string)
			key := ""
			for _, field := range options.GroupBy {
				var tagVal string
				switch val := rawPv[field].(type) {
				case nil:
					tagVal = ""
				case bool:
					if val {
						tagVal = "1"
					} else {
						tagVal = "0"
					}
				case string:
					tagVal = val
				case float64:
					tagVal = strconv.FormatFloat(val, 'f', 0, 64)
				case int64:
					tagVal = strconv.FormatInt(val, 10)
				default:
					return nil, fmt.Errorf("unhandled tag type in pageview listing: %T", rawPv[field])
				}

				tags[field] = fmt.Sprintf("%s", tagVal)
				key = fmt.Sprintf("%s%s=%s_", key, field, tagVal)
			}

			// place Pageview instance into proper PageviewRow based on tags (key)
			pr, ok := prBuckets[key]
			if !ok {
				pr = &PageviewRow{
					Tags: tags,
				}
				prBuckets[key] = pr
			}
			pr.Pageviews = append(pr.Pageviews, pv)
		}
	}

	for _, pr := range prBuckets {
		prc = append(prc, pr)
	}

	return prc, nil
}

// Categories lists all tracked categories.
func (pDB *PageviewElastic) Categories() []string {
	return []string{
		CategoryPageview,
	}
}

// Flags lists all available flags.
func (pDB *PageviewElastic) Flags() []string {
	return []string{
		FlagArticle,
	}
}

// Actions lists all tracked actions under the given category.
func (pDB *PageviewElastic) Actions(category string) ([]string, error) {
	switch category {
	case CategoryPageview:
		return []string{
			ActionPageviewLoad,
		}, nil
	}
	return nil, fmt.Errorf("unknown pageview category: %s", category)
}

// Users lists all tracked users.
func (pDB *PageviewElastic) Users() ([]string, error) {
	// prepare aggregation
	search := pDB.DB.Client.Search().Index("Pageviews").Type("_doc").Size(0)
	agg := elastic.NewTermsAggregation().Field("user_id.keyword")
	search = search.Aggregation("buckets", agg)

	// get results
	result, err := search.Do(pDB.DB.Context)
	if err != nil {
		return nil, err
	}
	aggResult, _ := result.Aggregations.Terms("buckets")

	users := []string{}
	for _, bucket := range aggResult.Buckets {
		key, ok := bucket.Key.(string) // non-nested aggregation has string key
		if !ok {
			return nil, fmt.Errorf("unexpected type of bucket key: %T", bucket.Key)
		}
		users = append(users, key)
	}

	return users, nil
}
